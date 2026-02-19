CryptoUA — One‑Click install for ISPmanager Lite 5 (без Node.js)

1) Завантаж ZIP у корінь домену (каталог сайту) і РОЗПАКУЙ.
2) Відкрий: https://YOUR-DOMAIN/install.php
3) Введи паролі admin/operator → Install.
4) Адмінка: https://YOUR-DOMAIN/admin

Telegram (опційно):
- Webhook URL: https://YOUR-DOMAIN/tg/webhook.php?token=SECRET
- setWebhook:
  https://api.telegram.org/bot<TOKEN>/setWebhook?url=https://YOUR-DOMAIN/tg/webhook.php?token=SECRET

Uploads:
- /uploads/ (має бути доступний запис для PHP)


[Fix] Якщо бачиш 'could not find driver' — у PHP немає драйвера PDO_SQLITE.
Рішення: або увімкнути pdo_sqlite/sqlite3 у PHP, або перевстановити через install.php з DB type = MySQL.
