<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/language.php';

$is_superadmin = ($_SESSION['role'] ?? '') === 'superadmin';
$company_id = $_SESSION['company_id'] ?? null;

// Hämta lista på företag för superadmin
$all_companies = [];
if ($is_superadmin) {
    try {
        $stmt = $pdo->query("SELECT id, name FROM boka_companies ORDER BY name");
        $all_companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Debug: Lista företagen i HTML-kommentar
        echo "<!-- Företag i boka_companies: ";
        foreach ($all_companies as $c) {
            echo "({$c['id']}) {$c['name']} ";
        }
        echo "-->";
    } catch (PDOException $e) {
        echo "<div class='alert alert-warning'>Företagsval är inaktiverat: " . htmlspecialchars($e->getMessage()) . "</div>";
        $all_companies = [];
    }
}

if (!$is_superadmin && ($_SESSION['role'] ?? '') !== 'admin') {
    echo "<div class='alert alert-danger'>Endast superadmin eller admin kan skapa kategorier.</div>";
    exit;
}

$msg = '';
// Hantera radering
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $cat_id = (int)$_GET['delete'];
    // Endast superadmin får ta bort globala, admin får ta bort sina egna
    if ($is_superadmin) {
        $pdo->prepare("DELETE FROM boka_categories WHERE id=?")->execute([$cat_id]);
        $msg = "<div class='alert alert-success'>Kategori raderad!</div>";
    } else {
        $pdo->prepare("DELETE FROM boka_categories WHERE id=? AND company_id=?")->execute([$cat_id, $company_id]);
        $msg = "<div class='alert alert-success'>Kategori raderad!</div>";
    }
}

// Hantera redigering
if (isset($_POST['edit_id']) && is_numeric($_POST['edit_id'])) {
    $edit_id = (int)$_POST['edit_id'];
    $edit_name = trim($_POST['edit_name'] ?? '');
    if ($is_superadmin) {
        $edit_company_id = isset($_POST['global']) ? null : ($_POST['company_select'] ?? null);
    } else {
        $edit_company_id = $company_id;
    }
    if ($edit_name !== '') {
        if ($is_superadmin) {
            $pdo->prepare("UPDATE boka_categories SET name=?, company_id=? WHERE id=?")->execute([$edit_name, $edit_company_id, $edit_id]);
        } else {
            $pdo->prepare("UPDATE boka_categories SET name=? WHERE id=? AND company_id=?")->execute([$edit_name, $edit_id, $company_id]);
        }
        $msg = "<div class='alert alert-success'>Kategori uppdaterad!</div>";
    }
}

// Hantera ny kategori
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['edit_id'])) {
    $name = trim($_POST['name'] ?? '');
    if ($is_superadmin) {
        $cat_company_id = isset($_POST['global']) ? null : ($_POST['company_select'] ?? null);
    } else {
        $cat_company_id = $company_id;
    }
    if ($name !== '') {
        $stmt = $pdo->prepare("INSERT INTO boka_categories (name, company_id) VALUES (?, ?)");
        $stmt->execute([$name, $cat_company_id]);
        $msg = "<div class='alert alert-success'>Kategori skapad!</div>";
    } else {
        $msg = "<div class='alert alert-danger'>Fyll i ett kategorinamn.</div>";
    }
}

// Kontrollera att kolumnen parent_id finns i boka_categories innan vi använder den
$has_parent_id = false;
try {
    $cols = $pdo->query("SHOW COLUMNS FROM boka_categories LIKE 'parent_id'")->fetch();
    $has_parent_id = $cols !== false;
} catch (PDOException $e) {
    $has_parent_id = false;
}

// Hantera ny underkategori
if (
    isset($_POST['parent_id']) &&
    isset($_POST['sub_name']) &&
    trim($_POST['sub_name']) !== ''
) {
    if ($has_parent_id) {
        $parent_id = (int)$_POST['parent_id'];
        $sub_name = trim($_POST['sub_name']);
        $sub_company_id = $is_superadmin
            ? (isset($_POST['sub_global']) ? null : ($_POST['sub_company_select'] ?? null))
            : $company_id;
        $stmt = $pdo->prepare("INSERT INTO boka_categories (name, company_id, parent_id) VALUES (?, ?, ?)");
        $stmt->execute([$sub_name, $sub_company_id, $parent_id]);
        $msg = "<div class='alert alert-success'>Underkategori skapad!</div>";
        // Håll kvar på samma huvudkategori efter insättning
        $show_sub_form = $parent_id;
    } else {
        $msg = "<div class='alert alert-danger'>Underkategorier stöds inte (parent_id-kolumnen saknas i databasen).<br>
        <b>Lösning:</b> Lägg till kolumnen <code>parent_id</code> i tabellen <code>boka_categories</code>.<br>
        <code>ALTER TABLE boka_categories ADD COLUMN parent_id INT NULL AFTER company_id;</code>
        </div>";
    }
}

// Kontrollera att kolumnen parent_id finns i boka_categories innan vi använder den
$has_parent_id = false;
try {
    $cols = $pdo->query("SHOW COLUMNS FROM boka_categories LIKE 'parent_id'")->fetch();
    $has_parent_id = $cols !== false;
} catch (PDOException $e) {
    $has_parent_id = false;
}

// Lista kategorier: globala + företagsspecifika (+ underkategorier om parent_id finns)
if ($has_parent_id) {
    if ($is_superadmin) {
        $categories = $pdo->query("SELECT id, name, company_id, parent_id FROM boka_categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare("SELECT id, name, company_id, parent_id FROM boka_categories WHERE company_id IS NULL OR company_id = ? ORDER BY name");
        $stmt->execute([$company_id]);
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Bygg trädstruktur för kategorier och underkategorier
    $category_tree = [];
    foreach ($categories as $cat) {
        if (empty($cat['parent_id'])) {
            $category_tree[$cat['id']] = $cat + ['subcategories' => []];
        }
    }
    foreach ($categories as $cat) {
        if (!empty($cat['parent_id']) && isset($category_tree[$cat['parent_id']])) {
            $category_tree[$cat['parent_id']]['subcategories'][] = $cat;
        }
    }
} else {
    // Om parent_id inte finns, visa bara vanliga kategorier
    if ($is_superadmin) {
        $categories = $pdo->query("SELECT id, name, company_id FROM boka_categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare("SELECT id, name, company_id FROM boka_categories WHERE company_id IS NULL OR company_id = ? ORDER BY name");
        $stmt->execute([$company_id]);
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    // Gör en enkel array för loop
    $category_tree = [];
    foreach ($categories as $cat) {
        $category_tree[$cat['id']] = $cat + ['subcategories' => []];
    }
}

// För redigering
$edit_category = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    if ($is_superadmin) {
        $stmt = $pdo->prepare("SELECT id, name, company_id FROM boka_categories WHERE id=?");
        $stmt->execute([$edit_id]);
    } else {
        $stmt = $pdo->prepare("SELECT id, name, company_id FROM boka_categories WHERE id=? AND (company_id IS NULL OR company_id=?)");
        $stmt->execute([$edit_id, $company_id]);
    }
    $edit_category = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Hantera POST för att visa formulär för att lägga till underkategori
$show_sub_form = isset($_POST['show_sub_form']) ? (int)$_POST['show_sub_form'] : null;

// Hantera POST för att visa alla huvudkategorier igen
if (isset($_POST['show_all_main'])) {
    $show_sub_form = null;
}
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'sv' ?>">
<head>
    <meta charset="UTF-8">
    <title>Skapa kategori</title>
    <link rel="stylesheet" href="includes/main.css">
    <?php include 'includes/company_style.php'; ?>
    <style>
        .create-category-content {
            max-width: 900px;
            margin: 40px auto;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 24px #0001;
            padding: 2em;
        }
        .category-list ul {
            list-style: none;
            padding: 0;
        }
        .category-list li {
            border-bottom: 1px solid #eee;
            margin-bottom: 0.5em;
            padding-bottom: 0.5em;
        }
        .main-category-row {
            display: flex;
            align-items: center;
            gap: 1.2em;
            padding: 0.6em 0.2em 0.6em 0.2em;
            background: #f7fafd;
            border-radius: 6px;
        }
        .main-category-name {
            font-size: 1.15em;
            font-weight: 600;
            color: #263238;
            flex: 1 1 auto;
            display: flex;
            align-items: center;
            gap: 0.7em;
        }
        .main-category-meta {
            font-size: 0.95em;
            color: #607d8b;
            margin-left: 0.5em;
        }
        .main-category-actions {
            display: flex;
            gap: 0.3em;
            align-items: center;
        }
        /* --- Snyggare knappar --- */
        .btn-edit, .btn-sub, .btn-delete, .show-subcats-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.3em;
            border: none;
            border-radius: 5px;
            font-size: 1em;
            font-weight: 500;
            padding: 0.35em 1.1em;
            cursor: pointer;
            transition: background 0.18s, box-shadow 0.18s;
            box-shadow: 0 2px 8px #0001;
            outline: none;
            text-decoration: none;
        }
        .btn-edit {
            background: linear-gradient(90deg, #1976d2 60%, #2196f3 100%);
            color: #fff !important;
        }
        .btn-edit:hover {
            background: linear-gradient(90deg, #1565c0 60%, #1976d2 100%);
            box-shadow: 0 4px 12px #1976d233;
        }
        .btn-sub {
            background: linear-gradient(90deg, #43a047 60%, #66bb6a 100%);
            color: #fff !important;
        }
        .btn-sub:hover {
            background: linear-gradient(90deg, #388e3c 60%, #43a047 100%);
            box-shadow: 0 4px 12px #43a04733;
        }
        .btn-delete {
            background: linear-gradient(90deg, #e53935 60%, #ff5252 100%);
            color: #fff !important;
        }
        .btn-delete:hover {
            background: linear-gradient(90deg, #b71c1c 60%, #e53935 100%);
            box-shadow: 0 4px 12px #e5393533;
        }
        .show-subcats-btn {
            background: linear-gradient(90deg, #f5f7fa 60%, #e3eaf2 100%);
            color: #1976d2;
            border: 1px solid #e0e0e0;
            font-size: 0.98em;
            padding: 0.35em 1.1em;
        }
        .show-subcats-btn:hover {
            background: linear-gradient(90deg, #e3eaf2 60%, #f5f7fa 100%);
            color: #1565c0;
            border-color: #b0bec5;
        }
        /* --- Övriga befintliga stilar --- */
        .category-actions form,
        .category-actions a {
            display: inline-block;
            margin-left: 0.1em;
        }
        .subcat-list {
            margin-left: 2.5em;
            margin-top: 0.5em;
        }
        .subcat-list li {
            border: none;
            padding: 0.2em 0;
            background: none;
        }
        .subcat-name {
            font-size: 1.05em;
            display: flex;
            align-items: center;
            gap: 0.5em;
        }
        .subcat-meta {
            font-size: 0.9em;
            color: #607d8b;
            margin-left: 0.5em;
        }
        .category-actions .btn-edit,
        .category-actions .btn-delete {
            margin-left: 0.2em;
        }
        .main-category-actions .btn-edit,
        .main-category-actions .btn-sub,
        .main-category-actions .btn-delete {
            margin-left: 0.2em;
        }
        .back-to-main {
            margin-bottom: 1em;
            display: flex;
            justify-content: flex-end;
        }
        .back-to-main button {
            background: linear-gradient(90deg, #1976d2 60%, #2196f3 100%);
            color: #fff;
            border: none;
            border-radius: 5px;
            padding: 0.35em 1.1em;
            font-size: 1em;
            font-weight: 500;
            cursor: pointer;
            box-shadow: 0 2px 8px #0001;
            transition: background 0.18s, box-shadow 0.18s;
        }
        .back-to-main button:hover {
            background: linear-gradient(90deg, #1565c0 60%, #1976d2 100%);
            box-shadow: 0 4px 12px #1976d233;
        }
        .create-category-content button,
        .create-category-content input[type="submit"] {
            width: 100%;
            padding: 0.8em;
            background: linear-gradient(90deg, #1976d2 60%, #2196f3 100%);
            color: #fff !important;
            border: none;
            border-radius: 5px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 2px 8px #0001;
            transition: background 0.18s, box-shadow 0.18s;
            margin-top: 0.5em;
        }
        .create-category-content button:hover,
        .create-category-content input[type="submit"]:hover {
            background: linear-gradient(90deg, #1565c0 60%, #1976d2 100%);
            color: #fff !important;
            box-shadow: 0 4px 12px #1976d233;
        }
    </style>
</head>
<body>
<div class="create-category-content">
    <h2>
        <?php if ($show_sub_form): ?>
            Skapa underkategori
        <?php else: ?>
            Skapa kategori
        <?php endif; ?>
    </h2>
    <?= $msg ?>
    <?php if ($edit_category): ?>
        <form method="post">
            <input type="hidden" name="edit_id" value="<?= $edit_category['id'] ?>">
            <label>Redigera kategorinamn
                <input type="text" name="edit_name" value="<?= htmlspecialchars($edit_category['name']) ?>" required>
            </label>
            <?php if ($is_superadmin): ?>
                <label style="font-weight:400;">
                    <input type="checkbox" name="global" value="1" <?= is_null($edit_category['company_id']) ? 'checked' : '' ?> onclick="toggleCompanySelect(this)">
                    Global kategori (tillgänglig för alla företag)
                </label>
                <label id="company_select_label" style="<?= is_null($edit_category['company_id']) ? 'display:none;' : '' ?>">
                    Företag:
                    <select name="company_select" style="width:100%;padding:0.7em;margin-bottom:1em;border:1px solid #bdbdbd;border-radius:5px;">
                        <option value="">-- Välj företag --</option>
                        <?php foreach ($all_companies as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($edit_category['company_id'] == $c['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <script>
                function toggleCompanySelect(checkbox) {
                    var label = document.getElementById('company_select_label');
                    if (checkbox.checked) {
                        label.style.display = 'none';
                    } else {
                        label.style.display = '';
                    }
                }
                </script>
            <?php endif; ?>
            <button type="submit" class="btn-edit">Spara ändring</button>
            <a href="dashboard.php?page=admin_create_category" style="margin-left:1em;">Avbryt</a>
        </form>
    <?php else: ?>
        <?php if (!$show_sub_form): ?>
            <form method="post">
                <label>Kategorinamn
                    <input type="text" name="name" required>
                </label>
                <?php if ($is_superadmin): ?>
                    <label style="font-weight:400;">
                        <input type="checkbox" name="global" value="1" <?= isset($_POST['name']) ? (isset($_POST['global']) ? 'checked' : '') : 'checked' ?> onclick="toggleCompanySelect(this)">
                        Global kategori (tillgänglig för alla företag)
                    </label>
                    <label id="company_select_label" style="<?= (isset($_POST['name']) && isset($_POST['global'])) || !isset($_POST['name']) ? 'display:none;' : '' ?>">
                        Företag:
                        <select name="company_select" style="width:100%;padding:0.7em;margin-bottom:1em;border:1px solid #bdbdbd;border-radius:5px;">
                            <option value="">-- Välj företag --</option>
                            <?php foreach ($all_companies as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= (isset($_POST['company_select']) && $_POST['company_select'] == $c['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <script>
                    function toggleCompanySelect(checkbox) {
                        var label = document.getElementById('company_select_label');
                        if (checkbox.checked) {
                            label.style.display = 'none';
                        } else {
                            label.style.display = '';
                        }
                    }
                    </script>
                <?php endif; ?>
                <button type="submit">Skapa kategori</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
    <div class="category-list">
        <h3>Befintliga kategorier</h3>
        <?php if ($show_sub_form): ?>
            <form method="post" class="back-to-main">
                <button type="submit" name="show_all_main">Tillbaka till huvudkategorier</button>
            </form>
        <?php endif; ?>
        <ul>
            <?php foreach ($category_tree as $cat): ?>
                <?php if ($show_sub_form && $show_sub_form !== $cat['id']) continue; ?>
                <li>
                    <div class="main-category-row">
                        <span class="main-category-name">
                            <?= htmlspecialchars($cat['name']) ?>
                            <?php if ($is_superadmin): ?>
                                <span class="main-category-meta">
                                    <?= empty($cat['company_id']) ? '(Global)' : '(Företagsspecifik)' ?>
                                </span>
                            <?php endif; ?>
                        </span>
                        <span class="main-category-actions">
                            <a href="dashboard.php?page=admin_create_category&edit=<?= $cat['id'] ?>" class="btn-edit" title="Redigera">&#9998;</a>
                            <?php if (!$show_sub_form): ?>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="show_sub_form" value="<?= $cat['id'] ?>">
                                    <button type="submit" class="btn-sub" title="Lägg till underkategori">+ Underkategori</button>
                                </form>
                            <?php endif; ?>
                            <a href="dashboard.php?page=admin_create_category&delete=<?= $cat['id'] ?>" class="btn-delete" title="Radera" onclick="return confirm('Vill du verkligen radera denna kategori?')">&#128465;</a>
                            <?php if (!empty($cat['subcategories'])): ?>
                                <button type="button" class="show-subcats-btn" onclick="this.parentNode.parentNode.parentNode.querySelector('.subcat-details').open = !this.parentNode.parentNode.parentNode.querySelector('.subcat-details').open;">
                                    Visa underkategorier (<?= count($cat['subcategories']) ?>)
                                </button>
                            <?php endif; ?>
                        </span>
                    </div>
                    <!-- Visa formulär för att lägga till underkategori om denna är vald -->
                    <?php if ($show_sub_form === $cat['id']): ?>
                        <form method="post" style="margin-top:0.5em;margin-bottom:0.5em;">
                            <input type="hidden" name="parent_id" value="<?= $cat['id'] ?>">
                            <input type="text" name="sub_name" placeholder="Ny underkategori" style="width:60%;padding:0.3em;">
                            <?php if ($is_superadmin): ?>
                                <label style="font-weight:400;font-size:0.95em;">
                                    <input type="checkbox" name="sub_global" value="1" checked onclick="toggleSubCompanySelect(this, <?= $cat['id'] ?>)">
                                    Global
                                </label>
                                <span id="sub_company_select_label_<?= $cat['id'] ?>" style="display:none;">
                                    <select name="sub_company_select" style="padding:0.3em;">
                                        <option value="">-- Välj företag --</option>
                                        <?php foreach ($all_companies as $c): ?>
                                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </span>
                                <script>
                                function toggleSubCompanySelect(checkbox, id) {
                                    var label = document.getElementById('sub_company_select_label_' + id);
                                    if (checkbox.checked) {
                                        label.style.display = 'none';
                                    } else {
                                        label.style.display = '';
                                    }
                                }
                                </script>
                            <?php endif; ?>
                            <button type="submit" style="padding:0.3em 1em;margin-left:0.5em;">Lägg till</button>
                        </form>
                    <?php endif; ?>
                    <!-- Underkategorier -->
                    <?php if (!empty($cat['subcategories'])): ?>
                        <details class="subcat-details" style="margin:0.5em 0 0.5em 0;">
                            <summary style="display:none;"></summary>
                            <ul class="subcat-list">
                                <?php foreach ($cat['subcategories'] as $sub): ?>
                                    <li class="<?= empty($sub['company_id']) ? 'global' : 'company' ?>">
                                        <span class="subcat-name">
                                            <span style="color:#bdbdbd;font-size:1.2em;vertical-align:middle;">&#8627;</span>
                                            <?= htmlspecialchars($sub['name']) ?>
                                            <?php if ($is_superadmin): ?>
                                                <span class="subcat-meta">
                                                    <?= empty($sub['company_id']) ? '(Global)' : '(Företagsspecifik)' ?>
                                                </span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="category-actions" style="margin-left:auto;">
                                            <a href="dashboard.php?page=admin_create_category&edit=<?= $sub['id'] ?>" class="btn-edit" title="Redigera">&#9998;</a>
                                            <a href="dashboard.php?page=admin_create_category&delete=<?= $sub['id'] ?>" class="btn-delete" title="Radera" onclick="return confirm('Vill du verkligen radera denna kategori?')">&#128465;</a>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </details>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<script>
    // Expandera underkategorier automatiskt om man är i "lägg till underkategori"-läge
    <?php if ($show_sub_form): ?>
    document.addEventListener('DOMContentLoaded', function() {
        var details = document.querySelector('li .subcat-details');
        if (details) details.open = true;
    });
    <?php endif; ?>
</script>
</body>
</html>
