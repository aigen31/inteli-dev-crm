# Подводные камни

- **Документация дрейфует.** `.github/instructions/*.md` полезны как бизнес-требования и UI-описание, но там Laravel 11, Inertia 1, Tailwind 3 и несуществующие файлы (`AuthServiceProvider`, `EventServiceProvider`, `RetryFailedPaymentsCommand`). Источник истины — код.
- **Compose требует `ACME_EMAIL` и `DB_PASSWORD`** даже для локального запуска: интерполяция выполняется до отключения Traefik профилем.
- **Каталоги должны быть в git:** `bootstrap/cache/`, `storage/framework/{sessions,views}`, `storage/logs/`. Без них падает `composer dump-autoload` или Laravel не пишет кеш/логи.
- **nginx и HTTPS.** Не хардкодьте `fastcgi_param HTTPS on`: локально это уводит редиректы на несуществующий https. nginx передаёт `X-Forwarded-Proto`; за Traefik протокол приходит в заголовке.
- **502 после пересоздания `app`.** nginx кеширует IP контейнера при старте. Перезапустите `nginx` после `docker compose up`, если upstream connection refused.
- **Ассеты и opcache.** `public/build` и PHP-код живут в образе; рестарт `app` возвращает версию из образа, а opcache не видит правок на лету. Подробности — [deployment.md](deployment.md).
- **Уведомления и UUID.** Пользователи используют UUID, поэтому `notifications.notifiable_id` — `uuidMorphs`, а не `morphs` (bigint). При добавлении новых морф-связей к пользователю проверяйте тип.
- **ЮКасса требует числовой shopId.** SDK присваивает его в типизированное `?int`; `YooKassaService` приводит значение и терпит пустые ключи, отдавая понятную ошибку вместо 500.
- **Прямой перевод — только ручное подтверждение.** Он не создаёт платёж в ЮКассе, не поддерживает рекуррент и не должен попадать в `pay`-роут.
- **`settings` — key-value таблица без кеша.** `SettingsService` читает значения в рамках запроса; при добавлении кеша не забудьте инвалидацию.
- **Промо-лендинг отдельный.** `promo/` — статический сайт в том же compose (порт 8080), не часть Inertia-приложения.
