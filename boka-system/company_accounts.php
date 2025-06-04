<?php
// ...existing code for authentication and DB connection...

$company_id = $_GET['id'] ?? null;
$selected_role = $_GET['role'] ?? 'Alla';

// Mock: Hämta konton kopplade till företag
$accounts = [
    ['id' => 1, 'name' => 'Anna Andersson', 'role' => 'Admin'],
    ['id' => 2, 'name' => 'Bertil Bengtsson', 'role' => 'User'],
    ['id' => 3, 'name' => 'Cecilia Carlsson', 'role' => 'User'],
    ['id' => 4, 'name' => 'David Dahl', 'role' => 'Manager']
];

// Filtrera på roll om vald
$roles = ['Alla', 'Admin', 'User', 'Manager'];
$filtered_accounts = ($selected_role === 'Alla')
    ? $accounts
    : array_filter($accounts, fn($a) => $a['role'] === $selected_role);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Kopplade konton</title>
</head>
<body>
    <h1>Kopplade konton till företag</h1>
    <form method="get">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($company_id); ?>">
        <label>
            Visa roll:
            <select name="role" onchange="this.form.submit()">
                <?php foreach ($roles as $role): ?>
                    <option value="<?php echo $role; ?>" <?php if ($selected_role === $role) echo 'selected'; ?>>
                        <?php echo $role; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>
    <ul>
        <?php foreach ($filtered_accounts as $acc): ?>
            <li><?php echo htmlspecialchars($acc['name']); ?> (<?php echo htmlspecialchars($acc['role']); ?>)</li>
        <?php endforeach; ?>
    </ul>
</body>
</html>
