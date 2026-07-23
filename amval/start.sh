#!/bin/bash
echo "🚀 راه‌اندازی سیستم مدیریت اموال"
echo "======================================"

# بررسی و اجرای MySQL
echo "📦 بررسی MySQL..."
if ps aux | grep -v grep | grep -q mysqld; then
    echo "   ✅ MySQL در حال اجراست"
else
    echo "   ⏳ در حال اجرای MySQL..."
    rm -f /tmp/mysql.sock /tmp/mysql.sock.lock 2>/dev/null
    mysqld --datadir=$PREFIX/var/lib/mysql --port=3306 --bind-address=127.0.0.1 --skip-grant-tables &
    sleep 6
    
    if ps aux | grep -v grep | grep -q mysqld; then
        echo "   ✅ MySQL اجرا شد"
    else
        echo "   ❌ MySQL اجرا نشد - دستی اجرا کن:"
        echo "   mysqld --port=3306 --bind-address=127.0.0.1 --skip-grant-tables &"
        exit 1
    fi
fi

# تست اتصال
if mysql -u root -h 127.0.0.1 -P 3306 -e "SELECT 1" 2>/dev/null; then
    echo "   ✅ اتصال به MySQL برقرار است"
else
    echo "   ⚠️ اتصال ناموفق - تلاش مجدد..."
    sleep 3
fi

echo ""
echo "🌐 اجرای وب سرور..."
echo "   آدرس: http://127.0.0.1:8080"
echo "   ورود: admin / admin123"
echo ""
echo "📴 برای توقف: Ctrl+C"
echo "======================================"
echo ""

# اجرای وب سرور
php -S 127.0.0.1:8080
