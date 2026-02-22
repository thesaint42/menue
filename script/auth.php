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
 * Check menu access and redirect to error page if denied
 * @param PDO $pdo Database connection
 * @param string|array $required_features Single feature or array of features
 * @param string $feature_type 'read' or 'write' (default: 'read')
 * @param string|null $prefix Database prefix
 */
function requireMenuAccess($pdo, $required_features, $feature_type = 'read', $prefix = null) {
    global $config;
    if (!$prefix) {
        $prefix = $config['database']['prefix'] ?? 'menu_';
    }
    
    if (!isLoggedIn()) {
        header("Location: ../error_access_denied.php?reason=" . urlencode('Sie müssen eingeloggt sein.') . "&feature=" . urlencode('Login erforderlich'));
        exit;
    }
    
    // Systemadmin (ID 1) hat immer Zugriff
    if ($_SESSION['role_id'] === 1) {
        return true;
    }
    
    // Normalize to array
    $features = is_array($required_features) ? $required_features : [$required_features];
    
    // Check each feature
    foreach ($features as $feature) {
        if (hasMenuAccess($pdo, $feature, $prefix)) {
            return true;
        }
    }
    
    // Zugriff verweigert
    $feature_display = implode(', ', $features);
    header("Location: ../error_access_denied.php?reason=" . urlencode('Sie haben keine Berechtigung, auf diese Seite zuzugreifen.') . "&feature=" . urlencode($feature_display));
    exit;
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
 * Check if the current user's role has access to a specific menu item
 * @param PDO $pdo Database connection
 * @param string $menu_key The menu key to check (e.g., 'projects_write')
 * @param string|null $prefix Database table prefix
 * @return bool True if access is granted, false otherwise
 */
function hasMenuAccess($pdo, $menu_key, $prefix = null) {
    global $config;
    if (!$prefix) {
        $prefix = $config['database']['prefix'] ?? 'menu_';
    }
    
    $role_id = $_SESSION['role_id'] ?? null;
    if (!$role_id) {
        return false;
    }
    
    // Systemadmin (role_id = 1) has access to everything
    if ($role_id === 1) {
        return true;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT visible FROM {$prefix}role_menu_access 
                             WHERE role_id = ? AND menu_key = ?");
        $stmt->execute([$role_id, $menu_key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result && $result['visible'];
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get all projects accessible to the current user
 * - Admin users (role_id = 1): all active projects
 * - Projektadmin/Reporter (ID 2/3): all assigned projects (including inactive)
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
    
    // Admin sees all active projects
    if ($role_id === 1) {
        $stmt = $pdo->query("SELECT id, name FROM {$prefix}projects WHERE is_active = 1 ORDER BY name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Projektadmin (ID 2) or Reporter (ID 3) - see ALL assigned projects (including inactive ones)
    // so they can re-activate them
    if ($role_id === 2 || $role_id === 3) {
        $stmt = $pdo->prepare("SELECT p.id, p.name FROM {$prefix}projects p 
                             INNER JOIN {$prefix}user_projects up ON p.id = up.project_id 
                             WHERE up.user_id = ? 
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
    
    // Check if user has project access via projects_write, projects_read, or legacy project_admin
    if (hasRoleFeature($pdo, 'project_admin', $prefix) || 
        hasMenuAccess($pdo, 'projects_write', $prefix) || 
        hasMenuAccess($pdo, 'projects_read', $prefix)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$prefix}user_projects 
                             WHERE user_id = ? AND project_id = ?");
        $stmt->execute([$user_id, $project_id]);
        return $stmt->fetchColumn() > 0;
    }
    
    return false;
}

/**
 * Check if v2.2.0 tables exist (role_features, role_menu_access, user_projects)
 * Returns: array with status of each table
 */
function checkV220Tables($pdo, $prefix = null) {
    global $config;
    if (!$prefix) {
        $prefix = $config['database']['prefix'] ?? 'menu_';
    }
    
    $result = [
        'role_features' => false,
        'role_menu_access' => false,
        'user_projects' => false,
    ];
    
    try {
        foreach (array_keys($result) as $table_name) {
            $stmt = $pdo->query("SHOW TABLES LIKE '{$prefix}{$table_name}'");
            $result[$table_name] = $stmt->rowCount() > 0;
        }
    } catch (Exception $e) {
        return $result;
    }
    
    return $result;
}

/**
 * Auto-initialize v2.2.0 tables if they don't exist
 * Tries to create tables on first access
 */
function initializeV220Tables($pdo, $prefix = null) {
    global $config;
    if (!$prefix) {
        $prefix = $config['database']['prefix'] ?? 'menu_';
    }
    
    $tables_ok = checkV220Tables($pdo, $prefix);
    if (!in_array(false, $tables_ok, true)) {
        return true; // All tables exist
    }
    
    try {
        // Create role_features table
        if (!$tables_ok['role_features']) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}role_features (
                id INT AUTO_INCREMENT PRIMARY KEY,
                role_id INT NOT NULL,
                feature_name VARCHAR(100) NOT NULL,
                enabled TINYINT DEFAULT 1,
                UNIQUE KEY (role_id, feature_name),
                FOREIGN KEY (role_id) REFERENCES {$prefix}roles(id) ON DELETE CASCADE
            )");
        }
        
        // Create role_menu_access table
        if (!$tables_ok['role_menu_access']) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}role_menu_access (
                id INT AUTO_INCREMENT PRIMARY KEY,
                role_id INT NOT NULL,
                menu_key VARCHAR(100) NOT NULL,
                visible TINYINT DEFAULT 1,
                UNIQUE KEY (role_id, menu_key),
                FOREIGN KEY (role_id) REFERENCES {$prefix}roles(id) ON DELETE CASCADE
            )");
        }
        
        // Create user_projects table
        if (!$tables_ok['user_projects']) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}user_projects (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                project_id INT NOT NULL,
                assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY (user_id, project_id),
                FOREIGN KEY (user_id) REFERENCES {$prefix}users(id) ON DELETE CASCADE,
                FOREIGN KEY (project_id) REFERENCES {$prefix}projects(id) ON DELETE CASCADE
            )");
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Failed to initialize v2.2.0 tables: " . $e->getMessage());
        return false;
    }
}
