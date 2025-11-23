<?php
/**
 * ═══════════════════════════════════════════════════════════════
 *         ADMIN REGISTRATION PAGE
 * ═══════════════════════════════════════════════════════════════
 */

 require_once '../../includes/config.php';
 require_once '../../includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        header('Location: ../admin/dashboard.php');
    } else {
        header('Location: /user/dashboard.php');
    }
    exit;
}

$page_title = 'Admin Registration';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - SmartAngler</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #6D94C5 0%, #3D5A80 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .register-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 100%;
            overflow: hidden;
        }

        .register-header {
            background: linear-gradient(135deg, #6D94C5 0%, #3D5A80 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }

        .register-header i {
            font-size: 60px;
            margin-bottom: 15px;
        }

        .register-header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .register-header p {
            font-size: 15px;
            opacity: 0.9;
        }

        .register-body {
            padding: 40px 30px;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert i {
            font-size: 20px;
        }

        .alert-error {
            background: #FADBD8;
            color: #E74C3C;
            border-left: 4px solid #E74C3C;
        }

        .alert-success {
            background: #D5F4E6;
            color: #27AE60;
            border-left: 4px solid #27AE60;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2C3E50;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group label .required {
            color: #E74C3C;
        }

        .form-group input {
            width: 100%;
            padding: 14px 15px;
            border: 2px solid #E0E0E0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #6D94C5;
            box-shadow: 0 0 0 3px rgba(109, 148, 197, 0.1);
        }

        .form-group small {
            display: block;
            margin-top: 5px;
            color: #7F8C8D;
            font-size: 12px;
        }

        .password-field {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #7F8C8D;
            font-size: 18px;
        }

        .password-toggle:hover {
            color: #6D94C5;
        }

        .btn-register {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #6D94C5 0%, #5A7BA8 100%);
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(109, 148, 197, 0.4);
        }

        .btn-register:disabled {
            background: #BDC3C7;
            cursor: not-allowed;
            transform: none;
        }

        .login-link {
            text-align: center;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid #E0E0E0;
        }

        .login-link a {
            color: #6D94C5;
            text-decoration: none;
            font-weight: 600;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .register-container {
                margin: 10px;
            }

            .register-header {
                padding: 30px 20px;
            }

            .register-header h1 {
                font-size: 24px;
            }

            .register-body {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <i class="fas fa-user-shield"></i>
            <h1>Admin Registration</h1>
            <p>Create your tournament admin account</p>
        </div>

        <div class="register-body">
            <div id="alertMessage"></div>

            <form id="adminRegisterForm" method="POST">
                <div class="form-group">
                    <label for="full_name">Full Name <span class="required">*</span></label>
                    <input type="text" id="full_name" name="full_name" required placeholder="Enter your full name">
                </div>

                <div class="form-group">
                    <label for="email">Email Address <span class="required">*</span></label>
                    <input type="email" id="email" name="email" required placeholder="your@email.com">
                </div>

                <div class="form-group">
                    <label for="phone_number">Phone Number <span class="required">*</span></label>
                    <input type="tel" id="phone_number" name="phone_number" required placeholder="012-345-6789">
                </div>

                <div class="form-group">
                    <label for="password">Password <span class="required">*</span></label>
                    <div class="password-field">
                        <input type="password" id="password" name="password" required placeholder="Enter password">
                        <i class="fas fa-eye password-toggle" onclick="togglePassword('password')"></i>
                    </div>
                    <small>Minimum 6 characters</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                    <div class="password-field">
                        <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm password">
                        <i class="fas fa-eye password-toggle" onclick="togglePassword('confirm_password')"></i>
                    </div>
                </div>

                <button type="submit" class="btn-register" id="submitBtn">
                    <i class="fas fa-user-plus"></i> Create Admin Account
                </button>
            </form>

            <div class="login-link">
                Already have an account? <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login Here</a>
            </div>
        </div>
    </div>

    <script>
        // Toggle password visibility
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling;
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Handle form submission
        document.getElementById('adminRegisterForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById('submitBtn');
            const alertMessage = document.getElementById('alertMessage');
            
            // Get form data
            const formData = new FormData(this);
            
            // Validate passwords match
            const password = formData.get('password');
            const confirmPassword = formData.get('confirm_password');
            
            if (password !== confirmPassword) {
                alertMessage.innerHTML = `
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span>Passwords do not match!</span>
                    </div>
                `;
                return;
            }

            // Validate password length
            if (password.length < 6) {
                alertMessage.innerHTML = `
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span>Password must be at least 6 characters long!</span>
                    </div>
                `;
                return;
            }
            
            // Disable submit button
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Account...';
            
            // Send request
            fetch('process-admin-register.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alertMessage.innerHTML = `
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <span>${data.message}</span>
                        </div>
                    `;
                    
                    // Redirect to login after 2 seconds
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 2000);
                } else {
                    alertMessage.innerHTML = `
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <span>${data.message}</span>
                        </div>
                    `;
                    
                    // Re-enable submit button
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-user-plus"></i> Create Admin Account';
                }
            })
            .catch(error => {
                alertMessage.innerHTML = `
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span>An error occurred. Please try again.</span>
                    </div>
                `;
                
                // Re-enable submit button
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-user-plus"></i> Create Admin Account';
            });
        });
    </script>
</body>
</html>