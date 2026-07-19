import os
import sys

# مسیرهای پیش‌فرض پروژه در ومپ‌سرور
WAMP64_BASE = r"C:\wamp64\www\amval"
WAMP_BASE = r"C:\wamp\www\amval"

# ساختار کارت‌های قدیمی برای جستجو
OLD_HTML_BLOCK = """<div class="center-grid">
<?php foreach($centers as $c): ?>
<a href="?center=<?=urlencode($c["center"])?>" class="center-card-item">
<span class="center-icon-big">🏢</span><div class="center-name"><?=htmlspecialchars($c["center"])?></div><span class="center-count"><?=$c["total"]?> مال</span>
</a>
<?php endforeach; ?>
</div>"""

# کدهای جدید دکمه‌های مینی‌مال، شیک و ۳ ستونه
NEW_HTML_BLOCK = """<style>
/* استایل دکمه‌های شیک و کم‌ارتفاع مراکز */
.center-btn {
    background: #fdfbf7 !important;
    border: 1.5px solid #cbd5e1 !important;
    border-radius: 10px !important;
    padding: 6px 8px !important;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 4px;
    text-decoration: none !important;
    height: 44px !important;
    box-shadow: none !important;
    transition: all 0.2s ease !important;
}
.center-btn:hover {
    background: #ffffff !important;
    border-color: #4f46e5 !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.1) !important;
}
</style>

<!-- دکمه‌های ۳تایی هم‌ردیف، باریک و شیک مراکز -->
<div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:8px; margin-bottom:16px;">
<?php foreach($centers as $c): ?>
<a href="?center=<?=urlencode($c["center"])?>" class="center-btn" title="<?=htmlspecialchars($c["center"])?>">
    <span style="font-weight:900 !important; font-size:11px !important; color:#1c1917 !important; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?=htmlspecialchars($c["center"])?></span>
    <span style="background:#e9e4d9 !important; color:#57534e !important; padding:2px 6px !important; border-radius:6px !important; font-size:10px !important; font-weight:900 !important; flex-shrink:0; border:1px solid #d4cebe !important;"><?=number_format($c["total"])?></span>
</a>
<?php endforeach; ?>
</div>"""

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

def restyle_centers():
    project_dir = get_project_dir()
    assets_file = os.path.join(project_dir, "assets.php")
    
    if os.path.exists(assets_file):
        try:
            with open(assets_file, "r", encoding="utf-8") as f:
                content = f.read()
            
            # همسان‌سازی نوع خطوط جهت افزایش دقت جایگزینی
            content_normalized = content.replace("\r\n", "\n")
            old_html = OLD_HTML_BLOCK.replace("\r\n", "\n")
            new_html = NEW_HTML_BLOCK.replace("\r\n", "\n")
            
            if old_html in content_normalized:
                content_normalized = content_normalized.replace(old_html, new_html)
                with open(assets_file, "w", encoding="utf-8", newline="") as f:
                    f.write(content_normalized)
                print("✅ کارت‌های پهن مراکز با موفقیت به دکمه‌های ۳ ستونه و مینی‌مال تبدیل شدند.")
            else:
                print("⚠️ الگوی کارت مراکز در فایل assets.php پیدا نشد (احتمالاً پچ قبلاً اعمال شده است).")
        except Exception as e:
            print(f"❌ خطا در زمان ویرایش فایل: {e}")
    else:
        print("❌ فایل assets.php یافت نشد.")

if __name__ == "__main__":
    restyle_centers()