#!/usr/bin/env python3
"""Проверка паритета разделов и общих компонентов между ролями CRM.

Смысл: любое изменение функционала должно одинаково доезжать до всех
пользователей. Скрипт ищет расхождения, которые появляются, когда для одной
роли переписали страницу/лейаут, а для другой забыли.

Что проверяется:
  1. Разделы по ролям из имён маршрутов (admin./client./executor.);
  2. Навигация не ветвится по исполнительской роли;
  3. Нет дублирующих лейаутов для пользователя (ExecutorLayout и т.п.);
  4. Страницы исполнителя используют общий лейаут;
  5. Одинаковые куски UI (карточка статуса, шаблонные секции) не размазаны
     по страницам вместо общего компонента.

Выход: 0 — чисто, 1 — есть critical.
"""
from __future__ import annotations

import argparse
import re
import sys
from collections import defaultdict
from pathlib import Path

ROLE_PREFIXES = {"admin": "admin.", "client": "client.", "executor": "executor."}
DOMAINS = [
    "dashboard", "tasks", "projects", "briefs", "reports", "invoices",
    "clients", "users", "forms", "settings", "assistant", "profile",
    "feedback", "help", "team", "balance",
]
DOMAIN_ALIASES = {"clients": "users", "team": "users"}

# Разделы, которые по бизнес-логике есть только у одной роли.
ALLOWED_ASYMMETRY = {
    "admin": {"clients", "users", "team", "forms", "settings"},
    "client": set(),
    "executor": set(),
}

DUPLICATE_UI_MARKERS = [
    # Смена статуса должна жить в одном компоненте, а не в каждой странице.
    ("смена статуса", re.compile(r"form\.patch\(route\([^)]*tasks\.status")),
]


def parse_routes(crm: Path) -> dict[str, set[str]]:
    """Собирает имена маршрутов с учётом групп `prefix(...)/name(...)`.

    В проекте имена внутри групп не всегда содержат префикс роли: группа
    задаёт его через `->name('admin.')`, поэтому наивный regex теряет
    маршруты. Идём по строкам и держим стек групп по отступу.
    """
    lines = (crm / "routes" / "web.php").read_text().splitlines()
    per_role: dict[str, set[str]] = defaultdict(set)
    stack: list[tuple[str, int]] = []

    for line in lines:
        indent = len(line) - len(line.lstrip())

        if stack and line.strip().startswith("});") and indent <= stack[-1][1]:
            stack.pop()
            continue

        group = re.search(r"->name\('(admin|client|executor)\.'\)", line) or re.search(
            r"prefix\('(admin|client|executor)'\)", line
        )
        if group and "group(function" in line:
            stack.append((group.group(1), indent))
            continue

        for name in re.findall(r"->name\('([^']+)'\)", line):
            prefix = stack[-1][0] + "." if stack else None
            full = name if prefix is None or name.startswith(prefix) else prefix + name
            matched = False
            for role, role_prefix in ROLE_PREFIXES.items():
                if full.startswith(role_prefix):
                    per_role[role].add(full)
                    matched = True
            if not matched:
                # Общие маршруты (например, balance.index) доступны всем ролям.
                for role in ROLE_PREFIXES:
                    per_role[role].add(full)
    return per_role


def domain_of_key(key: str) -> str:
    for part in key.split("."):
        if part in DOMAINS:
            return DOMAIN_ALIASES.get(part, part)

    first = key.split(".")[0]
    return DOMAIN_ALIASES.get(first, first)


def domains_of(names: set[str]) -> set[str]:
    result = set()
    for name in names:
        parts = name.split(".")
        for part in parts:
            if part in DOMAINS:
                result.add(DOMAIN_ALIASES.get(part, part))
                break
    return result


def check_nav(crm: Path, problems: list[str], warnings: list[str]) -> None:
    shell = (crm / "resources/js/Components/Layout/AppShell.vue").read_text()
    if "isExecutor" in shell or "role === 'executor'" in shell:
        problems.append(
            "AppShell всё ещё ветвит интерфейс по исполнительской роли — "
            "разделы должны быть едиными для всех пользователей."
        )
    if "'Пользователь'" not in shell:
        warnings.append("В AppShell нет единой подписи роли «Пользователь» — проверьте подписи в меню.")

    keys = re.findall(r"\{ key: '([^']+)'", shell)
    user_keys = [k for k in keys if not k.startswith("admin.")]
    if user_keys:
        nav_domains = {domain_of_key(k) for k in user_keys}
        print(f"  навигация пользователя: {', '.join(sorted(nav_domains))}")
        globals()["_nav_domains"] = nav_domains


def check_layouts(crm: Path, problems: list[str], warnings: list[str]) -> None:
    layouts = crm / "resources/js/Components/Layout"
    files = {p.name: p.read_text() for p in layouts.glob("*.vue")}
    for name in ("ExecutorLayout.vue", "ManagerLayout.vue"):
        if name in files:
            problems.append(
                f"Найден отдельный {name}: пользовательские лейауты должны быть общими "
                "(ClientLayout для всех не-админов)."
            )
    if "ClientLayout.vue" in files:
        for path in (crm / "resources/js/Pages/Executor").rglob("*.vue"):
            text = path.read_text()
            if "ClientLayout" not in text and "Layout" in text:
                warnings.append(
                    f"{path.relative_to(crm)} использует не общий ClientLayout — проверьте, "
                    "не расходится ли оформление с остальными разделами."
                )


def check_status_component(crm: Path, warnings: list[str]) -> None:
    for path in (crm / "resources/js/Pages").rglob("*.vue"):
        text = path.read_text()
        if "allowedTransitions" in text and "TaskStatusCard" not in text and "allowed_transitions" not in text:
            warnings.append(
                f"{path.relative_to(crm)} принимает переходы статусов, но не использует "
                "общий TaskStatusCard — правка статусов может разъехаться по ролям."
            )


def check_duplicate_ui(crm: Path, warnings: list[str]) -> None:
    for label, pattern in DUPLICATE_UI_MARKERS:
        hits = [
            p for p in (crm / "resources/js/Pages").rglob("*.vue")
            if pattern.search(p.read_text())
        ]
        if len(hits) > 2:
            names = ", ".join(str(p.relative_to(crm)) for p in hits[:4])
            warnings.append(
                f"UI «{label}» повторяется в {len(hits)} страницах ({names}) — "
                "вынесите в общий компонент, чтобы правка доезжала до всех ролей."
            )


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--crm", default=".")
    args = parser.parse_args()
    crm = Path(args.crm).resolve()

    problems: list[str] = []
    warnings: list[str] = []

    per_role = parse_routes(crm)
    domains = {role: domains_of(names) for role, names in per_role.items()}

    print("Разделы по ролям (из имён маршрутов):")
    for role in ("admin", "client", "executor"):
        extra = ALLOWED_ASYMMETRY.get(role, set())
        shown = sorted(domains.get(role, set()) - extra)
        print(f"  {role:9} {len(per_role.get(role, [])):3d} маршрутов · разделы: {', '.join(shown) or '—'}")

    # Ролей две: админ и пользователь. Пользователь ходит и в client.*, и в
    # executor.* маршруты, поэтому его разделы — объединение обоих пространств.
    user_domains = (domains.get("client", set()) | domains.get("executor", set())) - ALLOWED_ASYMMETRY.get("admin", set())
    admin_domains = domains.get("admin", set()) - ALLOWED_ASYMMETRY.get("admin", set())
    print(f"  пользователь: {', '.join(sorted(user_domains))}")

    nav_domains = globals().get("_nav_domains", set())
    ignored = {"profile", "assistant", "feedback", "help", "settings"}
    route_menu = user_domains - ignored
    if nav_domains and route_menu != nav_domains:
        problems.append(
            "Навигация пользователя и его маршруты расходятся: в меню "
            f"{sorted(nav_domains)}, доступно {sorted(route_menu)}."
        )

    executor_only = domains.get("executor", set()) - domains.get("client", set())
    if executor_only:
        warnings.append(
            "Разделы, доступные только через executor.* (проверьте, что они есть и в "
            "общем кабинете): " + ", ".join(sorted(executor_only))
        )

    check_nav(crm, problems, warnings)
    check_layouts(crm, problems, warnings)
    check_duplicate_ui(crm, warnings)
    check_status_component(crm, warnings)

    print()
    if problems:
        print(f"CRITICAL: {len(problems)}")
        for item in problems:
            print(" ✗", item)
    else:
        print("CRITICAL: none")

    if warnings:
        print(f"WARNINGS: {len(warnings)}")
        for item in warnings:
            print(" -", item)

    return 1 if problems else 0


if __name__ == "__main__":
    sys.exit(main())
