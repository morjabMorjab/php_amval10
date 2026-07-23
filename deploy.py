import os
import subprocess
import sys
from datetime import datetime

# مسیرهای پیش‌فرض پروژه در ومپ‌سرور
WAMP64_BASE = r"C:\wamp64\www\amval"
WAMP_BASE = r"C:\wamp\www\amval"
# آدرس مخزن گیت‌هاب شما
REPO_URL = "https://github.com/morjabMorjab/php_amval10.git"

# مشخصات اتصال دیتابیس سرور واقعی (تولید) جهت دپلوی نهایی
SERVER_DB_CONTENT = """<?php
function getDB() {
    static $db = null;
    if ($db !== null) return $db;
    try {
        // کانفیگ دیتابیس سرور واقعی (تولید)
        $db = new PDO("mysql:host=localhost;dbname=amval1_amval10;charset=utf8mb4", "amval1_amval", "morjab@414#mor", [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        return $db;
    } catch (PDOException $e) {
        return null;
    }
}
?>"""

def get_project_dir():
    if os.path.exists(WAMP64_BASE):
        return WAMP64_BASE
    elif os.path.exists(WAMP_BASE):
        return WAMP_BASE
    else:
        path = input("❌ مسیر پیش‌فرض پروژه یافت نشد. لطفاً مسیر پوشه پروژه را وارد کنید: ")
        if os.path.exists(path):
            return path
        sys.exit(1)

def run_git_command(args, cwd):
    try:
        result = subprocess.run(args, cwd=cwd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, check=True)
        return result.stdout.strip(), True
    except subprocess.CalledProcessError as e:
        return e.stderr.strip() + "\n" + e.stdout.strip(), False

def deploy_to_server():
    project_dir = get_project_dir()
    db_file = os.path.join(project_dir, "config", "database.php")
    
    if not os.path.exists(db_file):
        print("❌ فایل تنظیمات دیتابیس پیدا نشد.")
        return

    print("🔄 ۱. تهیه پشتیبان موقت از کانفیگ لوکال دیتابیس ومپ‌سرور...")
    try:
        with open(db_file, "r", encoding="utf-8") as f:
            local_backup = f.read()
    except Exception as e:
        print(f"❌ خطا در خواندن فایل دیتابیس لوکال: {e}")
        return

    print("🌐 ۲. اعمال موقت مشخصات دیتابیس سرور واقعی جهت ثبت در گیت...")
    try:
        with open(db_file, "w", encoding="utf-8") as f:
            f.write(SERVER_DB_CONTENT)
        print("   ✅ مشخصات دیتابیس سرور با موفقیت بر روی فایل قرار گرفت.")
    except Exception as e:
        print(f"❌ خطا در نوشتن تنظیمات سرور روی فایل: {e}")
        return

    # فرآیند استیج، کامیت و دپلوی کدهای سرور به گیت‌هاب
    print("📝 ۳. افزودن تغییرات به گیت و کامیت با مشخصات سرور...")
    run_git_command(["git", "add", "."], project_dir)
    
    now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    commit_msg = f"Production Deploy: synchronized assets code with server DB config on {now_str}"
    run_git_command(["git", "commit", "-m", commit_msg], project_dir)

    print("🚀 ۴. ارسال تغییرات به شاخه main گیت‌هاب جهت اعمال روی سرور...")
    stdout, success = run_git_command(["git", "push", "origin", "main"], project_dir)
    
    # مدیریت هوشمند گیت در صورت ناهمگام بودن با گیت‌هاب
    if not success and ("rejected" in stdout or "non-fast-forward" in stdout):
        print("⚠️ گیت‌هاب تغییرات را رد کرد. در حال همگام‌سازی خودکار تاریخچه با حفظ کدهای شما...")
        run_git_command([
            "git", "pull", "origin", "main", 
            "--allow-unrelated-histories", 
            "-X", "ours", 
            "--no-edit"
        ], project_dir)
        stdout, success = run_git_command(["git", "push", "origin", "main"], project_dir)

    # ۵. بازگردانی خودکار تنظیمات لوکال ومپ‌سرور جهت ممانعت از خرابی دیتابیس لوکال شما
    print("🏡 ۵. بازگردانی خودکار مشخصات دیتابیس لوکال ومپ‌سرور شما...")
    try:
        with open(db_file, "w", encoding="utf-8") as f:
            f.write(local_backup)
        print("   ✅ مشخصات دیتابیس لوکال با موفقیت به حالت اول بازگشت.")
    except Exception as e:
        print(f"❌ خطا در بازگردانی فایل دیتابیس لوکال: {e}")
        return

    if success:
        print("\n🎉 دپلوی به سرور با موفقیت به پایان رسید!")
        print("کدها با تنظیمات دیتابیس سرور واقعی روی گیت‌هاب آپلود شدند و دیتابیس لوکال شما همچنان فعال و بدون تغییر باقی ماند.")
    else:
        print(f"\n❌ خطا در فرآیند ارسال به گیت‌هاب: {stdout}")

if __name__ == "__main__":
    deploy_to_server()