import os
import subprocess
import sys
from datetime import datetime

# مسیرهای پیش‌فرض پروژه در ومپ‌سرور
WAMP64_BASE = r"C:\wamp64\www\amval"
WAMP_BASE = r"C:\wamp\www\amval"
# آدرس دقیق مخزن گیت‌هاب شما
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
        # اجرای دستورات گیت و دریافت خروجی
        result = subprocess.run(args, cwd=cwd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, check=True)
        return result.stdout.strip(), True
    except subprocess.CalledProcessError as e:
        return e.stderr.strip() + "\n" + e.stdout.strip(), False

def sync_to_github():
    project_dir = get_project_dir()
    print(f"📂 مسیر پروژه شناسایی شد: {project_dir}")
    print("⚡ شروع فرآیند ارسال تغییرات جدید به گیت‌هاب...")

    # ۱. اطمینان از وجود گیت در پروژه
    git_folder = os.path.join(project_dir, ".git")
    if not os.path.exists(git_folder):
        run_git_command(["git", "init"], project_dir)

    # ۲. تنظیم آدرس مخزن شما
    remotes, _ = run_git_command(["git", "remote"], project_dir)
    if "origin" not in remotes:
        run_git_command(["git", "remote", "add", "origin", REPO_URL], project_dir)
    else:
        run_git_command(["git", "remote", "set-url", "origin", REPO_URL], project_dir)

    # ۳. تنظیم روی شاخه main
    run_git_command(["git", "branch", "-M", "main"], project_dir)

    # ۴. استیج کردن تغییرات (اضافه کردن تمام فایل‌های اصلاح شده)
    print("📝 در حال افزودن فایل‌های ویرایش‌شده (UI جدید، دکمه‌های ۳تایی، فوتر و...) به گیت...")
    run_git_command(["git", "add", "."], project_dir)

    # ۵. کامیت کردن با پیام مشخص
    now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    commit_msg = f"Auto-deploy: Huge UI upgrade (3-column layout, Eye-care theme, bug fixes) on {now_str}"
    print(f"💾 ثبت محلی با پیام: '{commit_msg}'")
    stdout, success = run_git_command(["git", "commit", "-m", commit_msg], project_dir)
    
    if not success and ("nothing to commit" in stdout or "no changes added" in stdout):
        print("ℹ️ تغییر جدیدی نسبت به آخرین ارسال شما یافت نشد.")
    
    # ۶. پوش کردن به گیت‌هاب
    print("🚀 در حال ارسال (Push) به گیت‌هاب. لطفاً چند لحظه صبر کنید...")
    stdout, success = run_git_command(["git", "push", "-u", "origin", "main"], project_dir)
    
    # در صورتی که گیت‌هاب اخطار تداخل بدهد، این بخش کدهای جدید شما را به عنوان ارجحیت قرار می‌دهد
    if not success and ("rejected" in stdout or "non-fast-forward" in stdout):
        print("⚠️ گیت‌هاب دارای تغییراتی است که روی سیستم شما نیست. در حال ادغام هوشمند با اولویت کدهای شما...")
        run_git_command([
            "git", "pull", "origin", "main", 
            "--allow-unrelated-histories", 
            "-X", "ours", 
            "--no-edit"
        ], project_dir)
        
        # تلاش مجدد برای پوش
        stdout, success = run_git_command(["git", "push", "-u", "origin", "main"], project_dir)
        if success:
            print("🎉 عالی! تمام تغییرات و طراحی‌های جدید با موفقیت روی مخزن morjabMorjab/php_amval10 ذخیره شدند.")
        else:
            print(f"❌ خطا در ارسال نهایی: {stdout}")
    elif success:
        print("🎉 عالی! تمام تغییرات و طراحی‌های جدید با موفقیت روی مخزن morjabMorjab/php_amval10 در شاخه main ذخیره شدند.")
    else:
        print(f"\n❌ خطا در ارسال: {stdout}")

if __name__ == "__main__":
    sync_to_github()