<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account</title>
    <link rel="stylesheet" href="style.css">

    
</head>
<body>
    <div class="container">
        <h1 class="titre">  Register :</h1>
        <div class="form-box" id="login-form">
            <form action="register_process.php" method="POST">
                <input class="register" type="email" name="email" placeholder="Email" required>
                <input class="register" type="text" name="name" placeholder="Name" required>
                <input class="register" type="password" name="password" placeholder="Password" required>
                <button class="register" type="submit" name="Register">Register</button>
                <p id="phrase_register">Already have an account?  <a href="login.php">Login</a></p>
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
