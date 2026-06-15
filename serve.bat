@echo off
REM Start Laravel API on port 8001 with upload limits for gallery images (up to 10MB).
php -d upload_max_filesize=12M -d post_max_size=14M artisan serve --host=127.0.0.1 --port=8001
