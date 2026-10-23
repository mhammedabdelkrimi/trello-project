<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Trello Pro'; ?></title>
    <link rel="stylesheet" href="dashboard.css">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --nav-bg: rgba(0, 0, 0, 0.2);
            --nav-hover: rgba(255, 255, 255, 0.1);
        }
        body {
            font-family: 'Inter', sans-serif;
        }
        .navbar {
            background: var(--nav-bg);
            backdrop-filter: blur(10px);
            padding: 0.75rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .nav-logo {
            font-size: 1.5rem;
            font-weight: 800;
            text-decoration: none;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .nav-links {
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }
        .nav-link {
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            transition: all 0.2s;
        }
        .nav-link:hover {
            background: var(--nav-hover);
            color: white;
        }
        .nav-user {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.1);
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
        }
        .nav-avatar {
            width: 28px;
            height: 28px;
            background: var(--warning);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: white;
            font-size: 0.8rem;
        }
        .logout-btn {
            background: var(--danger);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: opacity 0.2s;
        }
        .logout-btn:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="dashboard.php" class="nav-logo">
            <i class="fa-solid fa-square-poll-horizontal"></i> Trello Pro
        </a>
        <div class="nav-links">
            <a href="dashboard.php" class="nav-link"><i class="fa-solid fa-house"></i> Dashboard</a>
            <div class="nav-user">
                <div class="nav-avatar">
                    <?php echo isset($_SESSION['user_name']) ? strtoupper(substr($_SESSION['user_name'], 0, 1)) : '?'; ?>
                </div>
                <span><?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'Invité'; ?></span>
            </div>
            <a href="logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Déconnexion</a>
        </div>
    </nav>
    <div class="container">
