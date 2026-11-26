#!/usr/bin/env python3
"""Агент сервера хранения: сканирует каталоги копий и отправляет манифест в CRM.

Конфиг /etc/inteli-backups/agent.json:
{
  "crm_url": "https://crm.inteli-dev.ru",
  "token": "…",
  "root": "/srv/inteli-backups",
  "targets": {"<service_uuid>": "<каталог>"},
  "timeout": 20
}

Запуск: backup_agent.py [--config PATH] [--dry-run]
"""
from __future__ import annotations

import argparse
import hashlib
import json
import os
import sys
import time
import urllib.error
import urllib.request
from datetime import datetime, timezone
from pathlib import Path


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def build_manifest(root: Path, targets: dict[str, str]) -> dict:
    entries = []
    for service_id, folder in targets.items():
        directory = root / folder
        files = []
        if directory.is_dir():
            for file in sorted(directory.glob("**/*"), key=lambda p: p.stat().st_mtime):
                if not file.is_file():
                    continue
                stat = file.stat()
                files.append({
                    "name": file.name,
                    "size": stat.st_size,
                    "uploaded_at": datetime.fromtimestamp(stat.st_mtime, tz=timezone.utc).isoformat(),
                    "sha256": sha256(file),
                })
        entries.append({"service_id": service_id, "files": files[-10:]})
    return {"targets": entries}


def post(url: str, token: str, payload: dict, timeout: int) -> dict:
    request = urllib.request.Request(
        url,
        data=json.dumps(payload).encode(),
        headers={"Content-Type": "application/json", "Authorization": f"Bearer {token}"},
        method="POST",
    )
    with urllib.request.urlopen(request, timeout=timeout) as response:
        return json.loads(response.read().decode())


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--config", default=os.environ.get("INTELI_BACKUP_AGENT_CONFIG", "/etc/inteli-backups/agent.json"))
    parser.add_argument("--dry-run", action="store_true")
    args = parser.parse_args()

    config = json.loads(Path(args.config).read_text())
    root = Path(config.get("root", "/srv/inteli-backups"))
    targets = config.get("targets", {})
    manifest = build_manifest(root, targets)

    if args.dry_run:
        print(json.dumps(manifest, ensure_ascii=False, indent=2))
        return 0

    try:
        result = post(
            config["crm_url"].rstrip("/") + "/api/infrastructure/report",
            config["token"],
            manifest,
            int(config.get("timeout", 20)),
        )
        print(json.dumps({"ok": True, "result": result, "at": time.strftime("%FT%TZ", time.gmtime())}))
        return 0
    except (urllib.error.URLError, urllib.error.HTTPError, KeyError) as error:
        print(json.dumps({"ok": False, "error": str(error)}), file=sys.stderr)
        return 1


if __name__ == "__main__":
    sys.exit(main())
