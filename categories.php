<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
checkAuth();

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save']) && isAdmin()) {
    $code = sanitize($_POST['code']);
    $name = sanitize($_POST['name']);
    $description = sanitize($_POST['description']);
    $parent_id = $_POST['parent_id'] ?: null;
    
    if (isset($_POST['id']) && $_POST['id']) {
        $stmt = $db->prepare("UPDATE categories SET code=?, name=?, description=?, parent_id=? WHERE id=?");
        $stmt->execute([$code, $name, $description, $parent_id, $_POST['id']]);
    } else {
        $stmt = $db->prepare("INSERT INTO categories (code, name, description, parent_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$code, $name, $description, $parent_id]);
    }
    redirect('categories.php');
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>دسته‌بندی اموال</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container">
        <div class="table-container">
            <div class="table-header">
                <h2>📂 دسته‌بندی اموال</h2>
                <?php if (isAdmin()): ?>
                <button class="btn btn-primary" onclick="openModal()">➕ دسته‌بندی جدید</button>
                <?php endif; ?>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>کد</th>
                        <th>نام</th>
                        <th>دسته والد</th>
                        <th>تعداد اموال</th>
                        <?php if (isAdmin()): ?>
                        <th>عملیات</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $db->query("
                        SELECT c.*, p.name as parent_name, COUNT(a.id) as asset_count 
                        FROM categories c 
                        LEFT JOIN categories p ON c.parent_id = p.id 
                        LEFT JOIN assets a ON c.id = a.category_id 
                        GROUP BY c.id 
                        ORDER BY c.name
                    ");
                    
                    while ($row = $stmt->fetch()) {
                        echo "<tr>";
                        echo "<td>{$row['code']}</td>";
                        echo "<td>{$row['name']}</td>";
                        echo "<td>{$row['parent_name']}</td>";
                        echo "<td>" . number_format($row['asset_count']) . "</td>";
                        if (isAdmin()) {
                            echo "<td>
                                <button class='btn btn-warning btn-sm' onclick='editCategory(" . json_encode($row) . ")'>✏️</button>
                            </td>";
                        }
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div id="categoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">دسته‌بندی جدید</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="id" id="categoryId">
                <div class="form-group">
                    <label>کد</label>
                    <input type="text" name="code" id="categoryCode" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>نام</label>
                    <input type="text" name="name" id="categoryName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>دسته والد</label>
                    <select name="parent_id" id="categoryParent" class="form-control">
                        <option value="">بدون والد</option>
                        <?php
                        $categories = $db->query("SELECT * FROM categories WHERE parent_id IS NULL");
                        while ($cat = $categories->fetch()) {
                            echo "<option value='{$cat['id']}'>{$cat['name']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>توضیحات</label>
                    <textarea name="description" id="categoryDescription" class="form-control" rows="3"></textarea>
                </div>
                <button type="submit" name="save" class="btn btn-primary btn-block">ذخیره</button>
            </form>
        </div>
    </div>
    
    <script>
        function openModal() {
            document.getElementById('categoryModal').style.display = 'block';
            document.getElementById('modalTitle').textContent = 'دسته‌بندی جدید';
        }
        
        function closeModal() {
            document.getElementById('categoryModal').style.display = 'none';
        }
        
        function editCategory(data) {
            document.getElementById('categoryModal').style.display = 'block';
            document.getElementById('modalTitle').textContent = 'ویرایش دسته‌بندی';
            document.getElementById('categoryId').value = data.id;
            document.getElementById('categoryCode').value = data.code;
            document.getElementById('categoryName').value = data.name;
            document.getElementById('categoryParent').value = data.parent_id || '';
            document.getElementById('categoryDescription').value = data.description || '';
        }
    </script>
</body>
</html>
