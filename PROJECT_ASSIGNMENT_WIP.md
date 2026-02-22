# Projektverwaltung - Project Assignment Feature (WIP)

## Status
**Work in Progress** - Framework in place, needs deployment testing and completion of project filtering on all dependent pages.

## Completed Components

### 1. Database Schema (✅ Deployed)
- **File**: `script/schema.php`
- **Change**: Added `user_projects` table (id, user_id FK, project_id FK, assigned_at timestamp)
- **UNIQUE constraint**: (user_id, project_id) - prevents duplicate assignments
- **Cascading delete**: ON DELETE CASCADE for both FKs

### 2. Users Management Interface (✅ Deployed)
- **File**: `admin/users.php`
- **Features**:
  - Detects "Projektverwaltung" role dynamically
  - Shows project checkboxes ONLY for Projektverwaltung users
  - Multiple project assignment via checkboxes
  - Edit mode toggles project selection enable/disable
  - JavaScript: `toggleProjectsSection()` handles role changes

**Backend Logic**:
- On user update: Checks if role is "Projektverwaltung"
- If Projektverwaltung: DELETE old assignments, INSERT new selected projects
- If role changes away: DELETE all project assignments

### 3. Authorization Functions (✅ Deployed)
- **File**: `script/auth.php`
- **Functions Added**:

```php
getUserProjects($pdo, $prefix)
  - Returns all accessible projects for current user
  - Admin: all projects
  - Projektverwaltung: only assigned projects
  - Others: empty array

hasProjectAccess($pdo, $project_id, $prefix)
  - Boolean check for project access
  - Used for authorization on protected operations
```

## TODO - Remaining Implementation

### 4. Project Filtering on All Pages (⏳ Not Started)

#### Priority 1 - Core Pages:
- **projects.php**: Filter dropdown to show only assigned projects
- **orders.php**: Filter dropdown to show only assigned projects  
- **guests.php**: Filter dropdown to show only assigned projects

#### Priority 2 - Content Management:
- **dishes.php**: Filter projects when managing dishes
- **menu_categories.php**: Filter project selection
- **reports.php**: Filter project selection for reports

#### Implementation Pattern:
```php
// Instead of:
$projects = $pdo->query("SELECT * FROM {$prefix}projects WHERE is_active = 1");

// Use:
$projects_list = getUserProjects($pdo, $prefix);
if (!$projects_list) {
    // Handle: user has no project access
    die("Keine Projekte zugewiesen");
}
// Create dropdown from $projects_list
```

### 5. Access Control (⏳ Not Started)
When user selects a project, validate access:
```php
if (!hasProjectAccess($pdo, $_GET['project'], $prefix)) {
    die("Zugriff verweigert");
}
```

### 6. Testing Scenarios (⏳ Not Started)
- [ ] Projektverwaltung user can only see assigned projects
- [ ] Admin can see all projects
- [ ] Projektverwaltung user cannot access URL with non-assigned project ID
- [ ] Reassignment of projects updates access correctly
- [ ] Project deletion cascade works correctly

### 7. Release (⏳ v2.2.0-stable)
- Document feature in README.md
- Create tag and release

## Testing Checklist

### Before Implementation of Filters:
- [ ] users.php loads without errors
- [ ] Can assign projects to Projektverwaltung user
- [ ] Project checkboxes visible when role is Projektverwaltung
- [ ] Project checkboxes hidden for other roles
- [ ] Can save project assignments
- [ ] Can edit and change project assignments
- [ ] Reassigning to different Projektverwaltung role works

### After Implementation of Filters:
- [ ] Projektverwaltung user sees only assigned projects in dropdown
- [ ] Admin sees all projects
- [ ] Project-specific URLs are protected
- [ ] Unassigned projects show error message
- [ ] Project changes reflected immediately in access

## Notes
- The `user_projects` table is CREATED but data is not migrated to existing databases
- Existing installations may need manual migration: `CREATE TABLE {prefix}user_projects (...)` 
- Consider adding migration helper in install.php or setup script
- Role name matching is case-insensitive to handle variations
