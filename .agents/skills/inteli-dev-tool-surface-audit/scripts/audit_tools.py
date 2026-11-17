#!/usr/bin/env python3
"""Static audit of inteli-dev tool surfaces.

Compares CRM MCP, CRM Kiki provider, site MCP and site kernel provider.
Exit code 1 when a critical capability is missing; 0 otherwise.
"""
from __future__ import annotations

import argparse
import json
import re
import subprocess
import sys
from pathlib import Path

DEFAULT_CRM = Path('/mnt/disk1/work/inteli-dev-infrastructure/inteli-dev-crm')
DEFAULT_KERNEL = Path('/mnt/disk1/work/inteli-dev-infrastructure/inteli-dev-kiki-assistant-kernel-api')
DEFAULT_SITE = Path('/mnt/disk1/work/inteli-dev-infrastructure/inteli-dev.ru')


def read(path: Path) -> str:
    return path.read_text(encoding='utf-8') if path.exists() else ''


def crm_mcp_tools(root: Path) -> list[str]:
    text = read(root / 'app/Services/Mcp/CrmMcpService.php')
    return re.findall(r"\$this->tool\('([^']+)'", text)


def crm_ai_tools(root: Path) -> list[str]:
    text = read(root / 'app/Services/Ai/CrmToolbox.php')
    start = text.find('private function catalog')
    if start < 0:
        return []
    end = text.find('private function', start + 10)
    block = text[start:end if end > start else len(text)]
    # Top-level catalog keys are indented by 12 spaces; nested fields are deeper.
    return re.findall(r"^            '([a-z_]+)' => \[", block, re.M)


def site_mcp_tools(root: Path) -> list[str]:
    text = read(root / 'src/mcp.rs')
    start = text.find('pub const TOOLS')
    block = text[start:]
    return re.findall(r'^\s*name: "([^"]+)"', block, re.M)


def site_provider(root: Path) -> tuple[str, list[str]]:
    text = read(root / 'src/api/kernel.rs')
    if 'crate::mcp::TOOLS' in text:
        return 'dynamic:all_site_mcp', []
    return 'static', re.findall(r'"name": "([^"]+)"', text)


def kernel_native(root: Path) -> list[str]:
    text = read(root / 'src/service.rs')
    return sorted(set(re.findall(r'"name": "(get_[a-z_]+)"', text)))


def changed(root: Path) -> list[str]:
    try:
        out = subprocess.check_output(
            ['git', '-C', str(root), 'log', '-1', '--name-only', '--pretty=format:'],
            text=True, stderr=subprocess.DEVNULL,
        )
        return [line for line in out.splitlines() if line.strip()]
    except Exception:
        return []


def diff(a: list[str], b: list[str]) -> tuple[list[str], list[str]]:
    sa, sb = set(a), set(b)
    return sorted(sa - sb), sorted(sb - sa)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument('--crm', type=Path, default=DEFAULT_CRM)
    parser.add_argument('--kernel', type=Path, default=DEFAULT_KERNEL)
    parser.add_argument('--site', type=Path, default=DEFAULT_SITE)
    parser.add_argument('--json', action='store_true')
    args = parser.parse_args()

    crm_mcp = crm_mcp_tools(args.crm)
    crm_ai = crm_ai_tools(args.crm)
    site_mcp = site_mcp_tools(args.site)
    site_mode, site_static = site_provider(args.site)
    kernel = kernel_native(args.kernel)

    site_kernel = site_mcp if site_mode.startswith('dynamic') else site_static
    critical: list[str] = []
    warnings: list[str] = []

    if not site_mcp:
        critical.append('site MCP registry is empty or unparsable')
    if not site_kernel:
        critical.append('site kernel provider exposes no tools')
    missing_site = sorted(set(site_mcp) - set(site_kernel))
    if missing_site:
        critical.append('site kernel misses MCP tools: ' + ', '.join(missing_site))

    for required in ['create_report', 'update_task', 'update_tasks', 'distribute_project_budget']:
        if required not in crm_ai:
            critical.append(f'CRM Kiki misses {required}')

    for required in ['create_report', 'create_task', 'crm_operation']:
        if required not in crm_mcp:
            critical.append(f'CRM MCP misses {required}')

    if 'get_price_list' not in kernel:
        warnings.append('kernel native get_price_list not found by static scan')

    provider_source = read(args.kernel / 'src/provider.rs')
    limit_match = re.search(r'MAX_PROVIDER_TOOLS:\s*usize\s*=\s*(\d+)', provider_source)
    provider_limit = int(limit_match.group(1)) if limit_match else 32
    if max(len(crm_ai), len(site_kernel)) > provider_limit:
        critical.append(
            f'provider exposes more tools ({max(len(crm_ai), len(site_kernel))}) than kernel limit ({provider_limit})'
        )

    ai_only, mcp_only = diff(crm_ai, crm_mcp)
    # Assistant-only tools are expected; do not fail the audit for them.
    known_ai_only = {
        'overview', 'list_form_templates', 'create_task_from_form', 'read_document',
        'propose_task_plan', 'study_project_site', 'crm_help', 'get_payment_link',
        'distribute_project_budget', 'update_task', 'update_tasks',
        'set_custom_field', 'apply_task_template',
        'add_project_member', 'archive_project', 'remove_project_member',
        'update_project', 'update_project_member',
    }
    known_mcp_only = {
        'create_project', 'create_project_invite', 'update_client', 'get_project',
        'get_invoice', 'get_report', 'list_users', 'list_forms',
        'get_custom_field_values', 'set_custom_field_values', 'crm_operation',
        'create_task_report', 'create_task_template', 'crm_overview',
        'list_forms', 'set_custom_field_values',
    }
    for name in ai_only:
        if name not in known_ai_only:
            warnings.append(f'Kiki-only tool needs review: {name}')
    for name in mcp_only:
        if name not in known_mcp_only:
            warnings.append(f'MCP-only tool needs review: {name}')

    payload = {
        'crm_mcp': crm_mcp,
        'crm_ai': crm_ai,
        'site_mcp': site_mcp,
        'site_kernel_mode': site_mode,
        'site_kernel': site_kernel,
        'kernel_native': kernel,
        'critical': critical,
        'warnings': warnings,
        'changed': {
            'crm': changed(args.crm),
            'kernel': changed(args.kernel),
            'site': changed(args.site),
        },
    }

    if args.json:
        print(json.dumps(payload, ensure_ascii=False, indent=2))
    else:
        print(f"CRM MCP: {len(crm_mcp)} | CRM Kiki: {len(crm_ai)} | Site MCP: {len(site_mcp)} | Site provider: {site_mode}")
        print(f"Kernel native: {', '.join(kernel) or '—'}")
        print(f"CRM MCP-only: {', '.join(mcp_only) or '—'}")
        print(f"CRM Kiki-only: {', '.join(ai_only) or '—'}")
        print()
        if critical:
            print('CRITICAL:')
            for item in critical:
                print(' -', item)
        else:
            print('CRITICAL: none')
        if warnings:
            print('WARNINGS:')
            for item in warnings:
                print(' -', item)
        else:
            print('WARNINGS: none')
        print('\nChanged in last commit:')
        for service, files in payload['changed'].items():
            print(f" - {service}: {', '.join(files) or '—'}")

    return 1 if critical else 0


if __name__ == '__main__':
    sys.exit(main())
