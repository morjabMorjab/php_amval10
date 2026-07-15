import os
import sys

# مسیرهای پیش‌فرض پروژه در ومپ‌سرور
WAMP64_BASE = r"C:\wamp64\www\amval"
WAMP_BASE = r"C:\wamp\www\amval"

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

def update_footer_padding():
    project_dir = get_project_dir()
    nav_file = os.path.join(project_dir, "includes", "bottom_nav.php")
    app_css = os.path.join(project_dir, "css", "app.css")
    
    # ۱. اصلاح پدینگ‌های درون‌خطی در فایل bottom_nav.php به ۱۰ پیکسل
    if os.path.exists(nav_file):
        try:
            with open(nav_file, "r", encoding="utf-8") as f:
                content = f.read()
            
            modified = False
            # جایگزینی پدینگ‌های قبلی به مقدار ۱۰ پیکسل
            if "padding:2px 4px;" in content:
                content = content.replace("padding:2px 4px;", "padding:10px 4px;")
                modified = True
            elif "padding:8px 4px;" in content:
                content = content.replace("padding:8px 4px;", "padding:10px 4px;")
                modified = True
                
            if "padding-bottom:max(2px," in content:
                content = content.replace("padding-bottom:max(2px,", "padding-bottom:max(10px,")
                modified = True
            elif "padding-bottom:max(8px," in content:
                content = content.replace("padding-bottom:max(8px,", "padding-bottom:max(10px,")
                modified = True
            
            if modified:
                with open(nav_file, "w", encoding="utf-8") as f:
                    f.write(content)
                print("✅ پدینگ عمودی منوی ناوبری در فایل bottom_nav.php به ۱۰ پیکسل تغییر یافت.")
            else:
                print("ℹ️ ساختار پدینگ درون‌خطی متفاوتی پیدا شد یا قبلاً به ۱۰ پیکسل تغییر یافته است.")
        except Exception as e:
            print(f"❌ خطا در ویرایش فایل bottom_nav.php: {e}")
            
    # ۲. اصلاح یا الحاق پدینگ کمکی ۱۰ پیکسلی در فایل استایل سراسری app.css
    if os.path.exists(app_css):
        try:
            with open(app_css, "r", encoding="utf-8") as f:
                content = f.read()
            
            # در صورتی که پدینگ ۲ پیکسلی قبلی در فایل باشد، آن را جایگزین می‌کند
            if "padding-top: 2px !important;" in content or "padding-bottom: 2px !important;" in content:
                content = content.replace("padding-top: 2px !important;", "padding-top: 10px !important;")
                content = content.replace("padding-bottom: 2px !important;", "padding-bottom: 10px !important;")
                with open(app_css, "w", encoding="utf-8") as f:
                    f.write(content)
                print("✅ پدینگ‌های قبلی در فایل app.css به ۱۰ پیکسل به‌روزرسانی شدند.")
            else:
                # در غیر این صورت، پدینگ ۱۰ پیکسلی جدید را الحاق می‌کند
                override_css = "\n\nnav { padding-top: 10px !important; padding-bottom: 10px !important; }\n"
                if "padding-top: 10px !important;" not in content:
                    with open(app_css, "a", encoding="utf-8") as f:
                        f.write(override_css)
                    print("✅ پدینگ ۱۰ پیکسلی کمکی مکمل به فایل app.css الحاق شد.")
        except Exception as e:
            print(f"❌ خطا در ویرایش فایل app.css: {e}")

if __name__ == "__main__":
    update_footer_padding()