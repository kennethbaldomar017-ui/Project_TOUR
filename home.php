<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home | PRIME.</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <?php if(isset($_SESSION['form_error'])): ?>
        <div class="form-errors" id="alertBox"><?= htmlspecialchars($_SESSION['form_error']); unset($_SESSION['form_error']); ?></div>
    <?php endif; ?>
    <?php if(isset($_SESSION['success'])): ?>
        <div class="form-success" id="alertBox"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <main class="container auth-page home-shell">
        <div class="auth-card hero-card">
            <div class="hero-section">
                <h1>Welcome to PRIME.</h1>
                <p>Your secure platform for identity management and authentication</p>
                
                <?php if(isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
                    <div>
                        <a href="dashboard.php" style="text-decoration: none;">
                            <button>Go to Dashboard</button>
                        </a>
                    </div>
                <?php else: ?>
                    <div>
                        <a href="login.php" style="text-decoration: none;">
                            <button>Log In</button>
                        </a>
                        <a href="sign.php" style="text-decoration: none;">
                            <button style="background: linear-gradient(135deg, var(--success) 0%, #15803d 100%);" id="homeRegisterBtn">Register</button>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php include 'footer.php'; ?>

    <script>
        // Check if lockout is active
        const lockoutRemaining = localStorage.getItem('lockoutRemaining');
        
        if (lockoutRemaining && parseInt(lockoutRemaining) > 0) {
            disableHomeRegisterButton();
            startLockoutTimer();
        }

        function disableHomeRegisterButton() {
            const homeRegisterBtn = document.getElementById('homeRegisterBtn');
            const headerRegisterBtn = document.querySelector('.top-nav a[href="sign.php"]');
            
            if (homeRegisterBtn) {
                homeRegisterBtn.style.pointerEvents = 'none';
                homeRegisterBtn.style.opacity = '0.5';
                homeRegisterBtn.style.cursor = 'not-allowed';
            }
            
            if (headerRegisterBtn) {
                headerRegisterBtn.style.pointerEvents = 'none';
                headerRegisterBtn.style.opacity = '0.5';
                headerRegisterBtn.style.cursor = 'not-allowed';
                headerRegisterBtn.title = 'Too many login attempts. Please try again later.';
            }
        }

        function enableHomeRegisterButton() {
            const homeRegisterBtn = document.getElementById('homeRegisterBtn');
            const headerRegisterBtn = document.querySelector('.top-nav a[href="sign.php"]');
            
            if (homeRegisterBtn) {
                homeRegisterBtn.style.pointerEvents = 'auto';
                homeRegisterBtn.style.opacity = '1';
                homeRegisterBtn.style.cursor = 'pointer';
            }
            
            if (headerRegisterBtn) {
                headerRegisterBtn.style.pointerEvents = 'auto';
                headerRegisterBtn.style.opacity = '1';
                headerRegisterBtn.style.cursor = 'pointer';
                headerRegisterBtn.title = '';
            }
        }

        function startLockoutTimer() {
            let remaining = parseInt(lockoutRemaining);
            const timer = setInterval(() => {
                remaining--;
                localStorage.setItem('lockoutRemaining', remaining);
                
                if (remaining <= 0) {
                    clearInterval(timer);
                    localStorage.removeItem('lockoutRemaining');
                    enableHomeRegisterButton();
                }
            }, 1000);
        }

        // Auto-dismiss alert after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alertBox = document.getElementById('alertBox');
            if (alertBox) {
                setTimeout(function() {
                    alertBox.classList.add('dismiss');
                    setTimeout(function() {
                        alertBox.style.display = 'none';
                    }, 400);
                }, 5000);
            }
        });
    </script>
</body>
</html>
