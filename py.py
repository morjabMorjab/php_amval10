import os
import sys
from ftplib import FTP, error_perm

# ==========================================================================
# تنظیمات - فقط فایل‌ها آپلود میشن، دیتابیس و کانفیگ دست‌نخورده میمونن
# ==========================================================================
LOCAL_PROJECT_DIR = r"C:\wamp64\www\amval"

# FTP Server
FTP_HOST = "ftp.amval10.ir"
FTP_PORT = 21
FTP_USER = "deploy@amval10.ir"
FTP_PASS = "Deploy@4#14"
REMOTE_DIR = "/public_html"

# فایل‌هایی که نباید آپلود بشن
EXCLUDE_FILES = [
    "deploy_to_server.py", "git_sync.py", "push_to_github.py", "py.py",
    "fix_all.php", "make_zip.php", "check.php", "test_db.php", "check_db.php",
    "run_now.php", "install_db.php", "install_db_new.php", "install_database.php",
    "create_admin.php", "reset_password.php", "revert_guide.txt",
    "users.php.bak", "app.css.bak", "app.css.old", "bottom_nav.php.bak",
    ".gitignore",
]

# پوشه‌هایی که نباید آپلود بشن (حفظ امنیت و تنظیمات هاست)
EXCLUDE_DIRS = [
    '__pycache__', 'node_modules', 'uploads', 'backup', 'backups', '.git',
    'config_website',  # پوشه اضافی
    'config',           # ⚠️ کل پوشه config آپلود نمیشه (database.php حفظ میشه)
]

# پسوندهایی که نباید آپلود بشن
EXCLUDE_EXTENSIONS = [".py", ".pyc", ".bak", ".old", ".zip", ".txt"]


def connect_ftp():
    """اتصال به FTP با تست روش‌های مختلف"""
    users = [FTP_USER, 'admin@amval10.ir', 'admin', 'amval1@amval10.ir']
    for user in users:
        try:
            ftp = FTP()
            ftp.connect(FTP_HOST, FTP_PORT, timeout=15)
            ftp.login(user, FTP_PASS)
            ftp.encoding = 'utf-8'
            print(f"✅ اتصال موفق با: {user}")
            return ftp
        except Exception:
            try:
                ftp.quit()
            except:
                pass
    return None


def make_remote_dir(ftp, path):
    """ساخت پوشه روی سرور"""
    dirs = [d for d in path.strip('/').split('/') if d]
    current = ''
    for d in dirs:
        current += '/' + d
        try:
            ftp.cwd(current)
        except error_perm:
            try:
                ftp.mkd(current)
                print(f"   📁 پوشه: {current}")
            except:
                pass


def upload_file(ftp, local_path, remote_path):
    """آپلود یک فایل"""
    try:
        with open(local_path, "rb") as f:
            ftp.storbinary(f"STOR {remote_path}", f)
        return True
    except Exception as e:
        print(f"   ❌ {os.path.basename(local_path)}: {e}")
        return False


def should_exclude(filename):
    """چک کن فایل باید رد بشه یا نه"""
    if filename.startswith('.'):
        return True
    if filename in EXCLUDE_FILES:
        return True
    if any(filename.endswith(ext) for ext in EXCLUDE_EXTENSIONS):
        return True
    return False


def deploy():
    """عملیات اصلی دیپلوی"""
    
    if not os.path.exists(LOCAL_PROJECT_DIR):
        print(f"❌ پوشه {LOCAL_PROJECT_DIR} یافت نشد!")
        sys.exit(1)

    print("=" * 60)
    print("🚀 دیپلوی فایل‌ها به amval10.ir")
    print("=" * 60)
    print(f"📁 لوکال: {LOCAL_PROJECT_DIR}")
    print(f"📁 ریموت: {REMOTE_DIR}")
    print(f"🔒 پوشه config آپلود نمیشه (database.php هاست حفظ میشه)")
    print(f"🔒 پوشه‌های اضافی آپلود نمیشن")
    print("=" * 60)

    # اتصال
    ftp = connect_ftp()
    if not ftp:
        print("\n❌ اتصال ناموفق!")
        print("💡 رمز FTP رو از cPanel چک کن یا یه FTP Account جدید بساز.")
        sys.exit(1)

    # آپلود
    total = 0
    uploaded = 0
    skipped = 0
    errors = 0

    for root, dirs, files in os.walk(LOCAL_PROJECT_DIR):
        # ⚠️ حذف پوشه‌های ممنوع (از جمله config)
        dirs[:] = [d for d in dirs if d not in EXCLUDE_DIRS]

        rel_path = os.path.relpath(root, LOCAL_PROJECT_DIR)
        remote_path = REMOTE_DIR.rstrip('/') + '/' + rel_path.replace('\\', '/')
        if rel_path == '.':
            remote_path = REMOTE_DIR

        make_remote_dir(ftp, remote_path)

        for file in files:
            total += 1

            if should_exclude(file):
                skipped += 1
                continue

            local_file = os.path.join(root, file)
            remote_file = remote_path.rstrip('/') + '/' + file

            if upload_file(ftp, local_file, remote_file):
                uploaded += 1
                if rel_path == '.':
                    print(f"   ✅ ({uploaded}/{total}) {file}")
                else:
                    print(f"   ✅ ({uploaded}/{total}) {rel_path}/{file}")
            else:
                errors += 1

    try:
        ftp.quit()
    except:
        pass

    print("\n" + "=" * 60)
    print(f"✅ آپلود شده: {uploaded}")
    print(f"⏭️ رد شده: {skipped}")
    print(f"❌ خطا: {errors}")
    print(f"📦 کل: {total}")
    print("=" * 60)
    print("🎉 دیپلوی با موفقیت انجام شد!")
    print(f"🌐 سایت: https://amval10.ir")
    print("=" * 60)


if __name__ == "__main__":
    deploy()