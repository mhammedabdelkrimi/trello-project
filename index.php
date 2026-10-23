<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="style.css">
    
</head>
<body>
    <div class="container">
        <h1 class="titre">Login :</h1>
        <div class="form-box" id="login-form">
            <form action="login_process.php" method="POST">
                <input  type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" name="Login">Login</button>
                <p id="phrase">Don't have an account?  <a href="register.php">Register</a></p>
                <p class="message">
                    <?php
                         if (isset($_GET['error'])) {
                         echo $_GET['error'];
                         }
                    ?>
                </p>
            </form>

        </div>
    </div>
    
</body>
</html>
