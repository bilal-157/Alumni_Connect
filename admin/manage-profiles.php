<?php
require_once 'auth_check.php';
require_once '../config/database.php';

// Get all profiles with user info
$sql = "
    SELECT p.*, u.name, u.email, u.role
    FROM profiles p
    JOIN users u ON p.user_id = u.id
    WHERE u.role != 'admin'
    ORDER BY u.name ASC
";
$stmt = $pdo->query($sql);
$profiles = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Profiles - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .sidebar a {
            color: white;
            text-decoration: none;
            padding: 10px 15px;
            display: block;
            transition: all 0.3s;
        }
        .sidebar a:hover, .sidebar a.active {
            background: rgba(255,255,255,0.1);
            padding-left: 25px;
        }
        .content-wrapper {
            background: #f8f9fa;
            min-height: 100vh;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 p-0 sidebar">
                <div class="text-center py-4">
                    <i class="bi bi-shield-lock-fill display-4 text-white"></i>
                    <h5 class="text-white mt-2">Admin Panel</h5>
                </div>
                <hr class="text-white-50">
                <nav>
                    <a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
                    <a href="manage-users.php"><i class="bi bi-people"></i> Manage Users</a>
                    <a href="manage-posts.php"><i class="bi bi-file-post"></i> Manage Posts</a>
                    <a href="manage-comments.php"><i class="bi bi-chat-dots"></i> Manage Comments</a>
                    <a href="manage-messages.php"><i class="bi bi-envelope"></i> Manage Messages</a>
                    <a href="manage-profiles.php" class="active"><i class="bi bi-person-badge"></i> Manage Profiles</a>
                    <hr class="text-white-50">
                    <a href="../dashboard.php"><i class="bi bi-house"></i> Back to Site</a>
                    <a href="../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 content-wrapper">
                <div class="p-4">
                    <h2 class="mb-4">
                        <i class="bi bi-person-badge text-danger"></i> Manage Profiles
                    </h2>

                    <!-- Profiles Table -->
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Batch</th>
                                            <th>Degree</th>
                                            <th>Job Title</th>
                                            <th>Company</th>
                                            <th>Skills</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($profiles)): ?>
                                            <tr>
                                                <td colspan="7" class="text-center">No profiles found</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($profiles as $profile): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= htmlspecialchars($profile['name']) ?></strong><br>
                                                        <small class="text-muted"><?= htmlspecialchars($profile['email']) ?></small>
                                                     </td>
                                                    <td><?= htmlspecialchars($profile['batch'] ?? 'Not set') ?></td>
                                                    <td><?= htmlspecialchars($profile['degree'] ?? 'Not set') ?></td>
                                                    <td><?= htmlspecialchars($profile['job_title'] ?? 'Not set') ?></td>
                                                    <td><?= htmlspecialchars($profile['company'] ?? 'Not set') ?></td>
                                                    <td><?= htmlspecialchars(substr($profile['skills'] ?? '', 0, 50)) ?></td>
                                                    <td>
                                                        <a href="../profile.php?id=<?= $profile['user_id'] ?>" target="_blank" class="btn btn-sm btn-info">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <a href="edit-profile.php?id=<?= $profile['user_id'] ?>" class="btn btn-sm btn-primary">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                     </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>