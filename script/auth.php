<?php
/**
 * script/auth.php - Authentifizierungsfunktionen
 */

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function setLogin($user_id, $email, $firstname, $lastname, $role_id) {
    $_SESSION['user_id'] = $user_id;
    $_SESSION['email'] = $email;
    $_SESSION['user_name'] = $firstname . ' ' . $lastname;
    $_SESSION['firstname'] = $firstname;
    $_SESSION['lastname'] = $lastname;
    $_SESSION['role_id'] = $role_id;
    $_SESSION['login_time'] = time();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getUserRole() {
    return $_SESSION['role_id'] ?? null;
}

function isAdmin() {
    return isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1;
}

/**
 * Check if current user has a specific feature/permission
 */
function hasRoleFeature($pdo, $feature_name, $prefix = null) {
    global $config;
    if (!$prefix) {
        $prefix = $config['database']['prefix'] ?? 'menu_';
    }
    
    if (!isLoggedIn()) {
        return false;
    }
    
    $role_id = getUserRole();
    
    // Admin role (ID=1) always has all features
    if ($role_id === 1) {
        return true;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT enabled FROM {$prefix}role_features 
                             WHERE role_id = ? AND feature_name = ? AND enabled = 1");
        $stmt->execute([$role_id, $feature_name]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        // role_features table doesn't exist yet - use fallback
        return false;
    }
}

/**
 * Get all features for a specific role
 */
function getRoleFeatures($pdo, $role_id, $prefix = null) {
    global $config;
    if (!$prefix) {
        $prefix = $config['database']['prefix'] ?? 'menu_';
    }
    
    try {
        $stmt = $pdo->prepare("SELECT feature_name, enabled FROM {$prefix}role_features 
                             WHERE role_id = ?");
        $stmt->execute([$role_id]);
        $features = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $features[$row['feature_name']] = $row['enabled'];
        }
        return $features;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get all projects accessible to the current user
 * - Admin users: all projects
 * - Users with 'project_admin' feature: only assigned projects
 * - Other users: no projects
 */
function getUserProjects($pdo, $prefix = null) {
    global $config;
    if (!$prefix) {
        $prefix = $config['database']['prefix'] ?? 'menu_';
    }
    
    if (!isLoggedIn()) {
        return [];
    }
    
    $user_id = getUserId();
    $role_id = getUserRole();
    
    // Admin sees all projects
    if ($role_id === 1) {
        $stmt = $pdo->query("SELECT id, name FROM {$prefix}projects WHERE is_active = 1 ORDER BY name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Check if user has 'project_admin' feature
    if (hasRoleFeature($pdo, 'project_admin', $prefix)) {
        $stmt = $pdo->prepare("SELECT p.id, p.name FROM {$prefix}projects p 
                             INNER JOIN {$prefix}user_projects up ON p.id = up.project_id 
                             WHERE up.user_id = ? AND p.is_active = 1 
                             ORDER BY p.name");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Other roles have no project access
    return [];
}

/**
 * Check if current user has access to a specific project
 */
function hasProjectAccess($pdo, $project_id, $prefix = null) {
    global $config;
    if (!$prefix) {
        $prefix = $config['database']['prefix'] ?? 'menu_';
    }
    
    if (!isLoggedIn()) {
        return false;
    }
    
    $user_id = getUserId();
    $role_id = getUserRole();
    
    // Admin has access to all projects
    if ($role_id === 1) {
        return true;
    }
    
    // Check if user has 'project_admin' feature with assigned project
    if (hasRoleFeature($pdo, 'project_admin', $prefix)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$prefix}user_projects 
                             WHERE user_id = ? AND project_id = ?");
        $stmt->execute([$user_id, $project_id]);
        return $stmt->fetchColumn() > 0;
    }
    
    return false;
}
