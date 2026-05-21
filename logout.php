<?php
session_start();

$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logging Out... | UTMSPACE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4/bootstrap-4.css">
    <style>
        :root {
            --utm-navy: #0B3B5E;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; overflow: hidden; }
        
        .blurry-bg {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-image: url('css/images/utm.jpg');
            background-size: cover; background-position: center;
            filter: blur(12px); transform: scale(1.1); z-index: 0;
        }
        
        .blurry-bg::after {
            content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(11, 59, 94, 0.75);
        }

        .premium-logout-popup {
            border-radius: 24px !important;
            padding: 2.5em 2em !important;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5) !important;
            border: 1px solid rgba(255,255,255,0.8) !important;
            background: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(10px) !important;
        }
        
        .premium-logout-progress {
            background: linear-gradient(90deg, #0B3B5E 0%, #3b82f6 100%) !important;
            height: 4px !important;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="blurry-bg"></div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                title: 'Signing Out',
                html: '<div class="mt-2 text-muted fw-semibold" style="font-size: 1.05rem;"><i class="fas fa-shield-alt me-2" style="color: #3b82f6;"></i>Securely clearing your session...</div>',
                timer: 1500,
                timerProgressBar: true,
                showConfirmButton: false,
                allowOutsideClick: false,
                allowEscapeKey: false,
                color: 'var(--utm-navy)',
                customClass: {
                    popup: 'premium-logout-popup',
                    timerProgressBar: 'premium-logout-progress'
                },
                didOpen: () => {
                    Swal.showLoading();
                }
            }).then(() => {
                window.location.href = 'login.php';
            });
        });
    </script>
</body>
</html>