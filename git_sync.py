import os
import subprocess
import sys
from datetime import datetime

# مسیرهای پیش‌فرض پروژه در ومپ‌سرور
WAMP64_BASE = r"C:\wamp64\www\amval"
WAMP_BASE = r"C:\wamp\www\amval"
# آدرس مخزن گیت‌هاب هدف شما
REPO_URL = "https://github.com/morjabMorjab/php_amval10.git"

def get_project_dir():
    if os.path.exists(WAMP64_BASE):
        return WAMP64_BASE
    elif os.path.exists(WAMP_BASE):
        return WAMP_BASE
    else:
        print("❌ پوشه پروژه در مسیرهای پیش‌فرض ومپ‌سرور پیدا نشد.")
        user_input = input("لطفاً مسیر پوشه پروژه خود را به صورت دستی وارد کنید (مثال C:\\wamp64\\www\\amval): ")
        if os.path.exists(user_input):
            return user_input
        print("❌ مسیر وارد شده نامعتبر است.")
        sys.exit(1)

def run_git_command(args, cwd):
    try:
        # اجرای دستورات گیت با خروجی و بررسی خطاها
        result = subprocess.run(args, cwd=cwd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, check=True)
        return result.stdout.strip(), True
    except subprocess.CalledProcessError as e:
        return e.stderr.strip(), False

def sync_to_github():
    project_dir = get_project_dir()
    print(f"📂 پوشه پروژه شناسایی شد: {project_dir}")
    print("⚡ شروع فرآیند همگام‌سازی و دپلوی روی گیت‌هاب...")

    # ۱. بررسی مقداردهی اولیه گیت (git init)
    git_folder = os.path.join(project_dir, ".git")
    if not os.path.exists(git_folder):
        print("⚙️ گیت در این پوشه تعریف نشده است. در حال اجرای git init...")
        run_git_command(["git", "init"], project_dir)

    # ۲. بررسی و تنظیم آدرس مخزن گیت‌هاب (remote origin)
    remotes, success = run_git_command(["git", "remote"], project_dir)
    if "origin" not in remotes:
        print(f"🌐 تنظیم آدرس مخزن گیت‌هاب روی {REPO_URL}...")
        run_git_command(["git", "remote", "add", "origin", REPO_URL], project_dir)
    else:
        # بررسی صحت آدرس در صورت وجود داشتن origin از قبل
        remote_url, _ = run_git_command(["git", "remote", "get-url", "origin"], project_dir)
        if remote_url != REPO_URL:
            print("🔄 به‌روزرسانی آدرس مخزن به آدرس گیت‌هاب جدید شما...")
            run_git_command(["git", "remote", "set-url", "origin", REPO_URL], project_dir)

    # ۳. تنظیم شاخه اصلی به main
    run_git_command(["git", "branch", "-M", "main"], project_dir)

    # ۴. افزودن تغییرات به استیج (git add .)
    print("📝 در حال افزودن فایل‌های تغییر یافته به گیت...")
    run_git_command(["git", "add", "."], project_dir)

    # ۵. ثبت کامیت با برچسب زمان دقیق اجرا
    now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    commit_msg = f"Auto-deploy: updated and optimized project files on {now_str}"
    print(f"💾 ثبت تغییرات محلی (Commit) با پیام: '{commit_msg}'...")
    stdout, success = run_git_command(["git", "commit", "-m", commit_msg], project_dir)
    
    if not success and ("nothing to commit" in stdout or "no changes added" in stdout):
        print("ℹ️ تغییر جدیدی در فایل‌های پروژه نسبت به کامیت قبلی یافت نشد.")
        return

    # ۶. ارسال تغییرات به گیت‌هاب (git push origin main)
    print("🚀 در حال ارسال تغییرات به شاخه main در گیت‌هاب شما (git push)...")
    print("⚠️ توجه: اگر برای اولین بار است، ممکن است ویندوز یا گیت از شما دسترسی ورود (Credentials) بپرسد.")
    stdout, success = run_git_command(["git", "push", "-u", "origin", "main"], project_dir)
    
    if success:
        print("\n🎉 همگام‌سازی موفقیت‌آمیز بود! تغییرات در مخزن morjabMorjab/php_amval10 شاخه main ذخیره شدند.")
    else:
        print(f"\n❌ خطا در هنگام ارسال به گیت‌هاب: {stdout}")
        print("💡 راه حل: مطمئن شوید ابزار Git روی ویندوز شما نصب است و دسترسی لازم برای نوشتن (Write) در این مخزن گیت‌هاب را دارید.")

if __name__ == "__main__":
    sync_to_github()