import os
import sys

# مسیرهای پیش‌فرض پروژه در ومپ‌سرور
WAMP64_BASE = r"C:\wamp64\www\amval"
WAMP_BASE = r"C:\wamp\www\amval"

# کدهای استایل هماهنگ‌کننده پلاک، نام و تگ‌های اطلاعاتی (لبه ۵ پیکسلی و بک‌گراند یکنواخت)
PATCH_CSS = """
/* ==========================================================================
   PATCH_SPECIFIC_THEME_09: یکسان‌سازی استایل، رنگ خاکستری و لبه ۵ پیکسل برای پلاک، نام و متادیتا
   ========================================================================== */
.meta-tag, .plate-badge, .asset-name {
    background: #e2e8f0 !important;   /* رنگ خاکستری ملایم کاملاً هماهنگ */
    border-radius: 5px !important;     /* لبه‌های گرد ۵ پیکسلی برای تمام بخش‌ها */
    color: #000000 !important;          /* متن تیره با کنتراست عالی */
    padding: 4px 8px !important;        /* پدینگ عمودی و افقی کاملاً یکسان */
    font-weight: bold !important;       /* متون ضخیم و خوانا */
    border: none !important;
}
"""

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
        print("❌ مسیر نامعتبر است.")
        sys.exit(1)

def apply_patch():
    project_dir = get_project_dir()
    app_css_path = os.path.join(project_dir, "css", "app.css")
    
    if os.path.exists(app_css_path):
        try:
            with open(app_css_path, "r", encoding="utf-8") as f:
                content = f.read()
            
            # جلوگیری از تکرار چندباره کد در صورت اجرای مکرر اسکریپت
            if "PATCH_SPECIFIC_THEME_09" not in content:
                with open(app_css_path, "a", encoding="utf-8") as f:
                    f.write(PATCH_CSS)
                print("✅ پچ یکسان‌سازی گرافیکی و لبه‌های ۵ پیکسلی تگ‌ها با موفقیت اعمال شد.")
            else:
                print("ℹ️ این پچ قبلاً روی فایل app.css اعمال شده است.")
        except Exception as e:
            print(f"❌ خطا در ویرایش فایل app.css: {e}")
    else:
        print("❌ فایل css/app.css یافت نشد.")

if __name__ == "__main__":
    apply_patch()