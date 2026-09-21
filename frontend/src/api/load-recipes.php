<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

header('Content-Type: application/json');

$user_id = getCurrentUserId();
$page = max(2, (int)($_GET['page'] ?? 2));
$page_size = 12;
$offset = ($page - 1) * $page_size;
$search = trim($_GET['q'] ?? '');
$filter_category = trim($_GET['category'] ?? '');
$filter_cuisine = trim($_GET['cuisine'] ?? '');
$filter_favorite = ($_GET['favorite'] ?? '') === '1';
$filter_cooked = ($_GET['cooked'] ?? '') === '1';

try {
    $db = getDB();
    $sql = "
        SELECT r.id, r.user_id, r.name, r.description, r.image_url, r.prep_time, r.cook_time, r.total_time,
               r.recipe_yield, r.recipe_category, r.recipe_cuisine,
               COALESCE(urs.is_favorite, FALSE) as is_favorite,
               COALESCE(urs.already_cooked, FALSE) as already_cooked,
               r.schema_data, r.created_at
        FROM recipes r
        LEFT JOIN user_recipe_status urs ON urs.recipe_id = r.id AND urs.user_id = :current_uid
        WHERE 1=1
    ";
    $params = [':current_uid' => $user_id ?: 0];

    if ($search !== '') {
        $sql .= " AND (LOWER(r.name) LIKE :q OR LOWER(r.description) LIKE :q OR r.schema_data::text ILIKE :q)";
        $params[':q'] = '%' . strtolower($search) . '%';
    }
    if ($filter_category !== '') {
        $sql .= " AND r.recipe_category = :category";
        $params[':category'] = $filter_category;
    }
    if ($filter_cuisine !== '') {
        $sql .= " AND r.recipe_cuisine = :cuisine";
        $params[':cuisine'] = $filter_cuisine;
    }
    if ($filter_favorite) {
        $sql .= " AND urs.is_favorite = TRUE";
    }
    if ($filter_cooked) {
        $sql .= " AND urs.already_cooked = TRUE";
    }

    $sql .= " ORDER BY is_favorite DESC, r.created_at DESC LIMIT " . ($page_size + 1) . " OFFSET " . $offset;
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $recipes = $stmt->fetchAll();
    $has_more = count($recipes) > $page_size;
    if ($has_more) {
        array_pop($recipes);
    }

    ob_start();
    foreach ($recipes as $recipe) {
        require __DIR__ . '/../includes/recipe-card.php';
    }
    $html = ob_get_clean();

    echo json_encode([
        'success' => true,
        'html' => $html,
        'has_more' => $has_more,
        'next_page' => $page + 1,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
