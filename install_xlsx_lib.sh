#!/bin/bash
echo "📦 نصب کتابخانه XLSX Reader..."

cd /storage/emulated/0/Download/amvalWeb

# دانلود SimpleXLSX (کتابخانه ساده و بدون نیاز به composer)
curl -L -o includes/SimpleXLSX.php https://raw.githubusercontent.com/shuchkin/simplexlsx/master/src/SimpleXLSX.php

if [ -f "includes/SimpleXLSX.php" ]; then
    echo "✅ کتابخانه XLSX با موفقیت نصب شد"
else
    echo "❌ خطا در دانلود. دستی دانلود کن:"
    echo "https://github.com/shuchkin/simplexlsx/raw/master/src/SimpleXLSX.php"
    echo "و در پوشه includes قرار بده"
fi
