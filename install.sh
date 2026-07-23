#!/bin/bash

echo "🚀 نصب سیستم مدیریت اموال"
echo "=============================="

# بررسی PHP
if ! command -v php &> /dev/null; then
    echo "❌ PHP نصب نیست. لطفاً PHP را نصب کنید."
    exit 1
fi

# بررسی MySQL
if ! command -v mysql &> /dev/null; then
    echo "❌ MySQL نصب نیست. لطفاً MySQL را نصب کنید."
    exit 1
fi

# ایجاد دیتابیس
echo "📁 ایجاد دیتابیس..."
mysql -u root < config/database.sql

if [ $? -eq 0 ]; then
    echo "✅ دیتابیس با موفقیت ایجاد شد"
else
    echo "❌ خطا در ایجاد دیتابیس"
    exit 1
fi

echo ""
echo "✅ نصب با موفقیت انجام شد"
echo ""
echo "📋 اطلاعات ورود:"
echo "   نام کاربری: admin"
echo "   رمز عبور: admin123"
echo ""
echo "🌐 برای اجرا:"
echo "   cd /storage/emulated/0/Download/amvalWeb"
echo "   php -S localhost:8080"
echo ""
echo "سپس در مرورگر آدرس localhost:8080 را باز کنید"
