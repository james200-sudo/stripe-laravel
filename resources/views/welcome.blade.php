<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hydro AI - Backend API</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #0a4d3c 0%, #1a7a5e 100%);
            color: #fff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .logo {
            font-size: 3rem;
            font-weight: bold;
            margin-bottom: 1rem;
            color: #4ade80;
        }

        h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .subtitle {
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 2rem;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(74, 222, 128, 0.2);
            border: 1px solid #4ade80;
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            font-weight: 500;
            margin-bottom: 2rem;
        }

        .status-dot {
            width: 10px;
            height: 10px;
            background: #4ade80;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 1rem;
            padding: 2rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .card-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .card h3 {
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
            color: #4ade80;
        }

        .card p {
            color: rgba(255, 255, 255, 0.8);
            line-height: 1.6;
        }

        .api-info {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .api-info h2 {
            color: #4ade80;
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }

        .endpoint {
            background: rgba(255, 255, 255, 0.05);
            border-left: 3px solid #4ade80;
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 0.5rem;
            font-family: 'Courier New', monospace;
        }

        .method {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 0.25rem;
            font-weight: bold;
            font-size: 0.875rem;
            margin-right: 0.5rem;
        }

        .method-get { background: #3b82f6; }
        .method-post { background: #10b981; }

        .endpoint-path {
            color: #fbbf24;
        }

        .endpoint-desc {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
            margin-top: 0.5rem;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        footer {
            text-align: center;
            padding: 2rem;
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.9rem;
        }

        .tech-stack {
            display: flex;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 2rem;
        }

        .tech-badge {
            background: rgba(255, 255, 255, 0.1);
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        @media (max-width: 768px) {
            h1 { font-size: 2rem; }
            .subtitle { font-size: 1rem; }
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="logo">⚡ Hydro AI</div>
            <h1>Backend API</h1>
            <p class="subtitle">Intelligent Hydropower Management System</p>
            <div class="status-badge">
                <span class="status-dot"></span>
                API Operational
            </div>
        </header>

        <div class="grid">
            <div class="card">
                <div class="card-icon">🔐</div>
                <h3>Secure Authentication</h3>
                <p>JWT-based authentication with Laravel Sanctum for secure API access and user management.</p>
            </div>

            <div class="card">
                <div class="card-icon">💳</div>
                <h3>Stripe Integration</h3>
                <p>Complete payment processing with subscription management for Individual, Company, and Utility plans.</p>
            </div>

            <div class="card">
                <div class="card-icon">📊</div>
                <h3>Plan Management</h3>
                <p>Flexible pricing tiers with monthly and yearly billing options tailored for hydropower operations.</p>
            </div>

            <div class="card">
                <div class="card-icon">🔄</div>
                <h3>Real-time Webhooks</h3>
                <p>Automated subscription updates via Stripe webhooks for seamless payment processing.</p>
            </div>
        </div>

        <div class="api-info">
            <h2>📡 API Endpoints</h2>
            
            <div class="endpoint">
                <span class="method method-post">POST</span>
                <span class="endpoint-path">/api/register</span>
                <div class="endpoint-desc">Create a new user account</div>
            </div>

            <div class="endpoint">
                <span class="method method-post">POST</span>
                <span class="endpoint-path">/api/login</span>
                <div class="endpoint-desc">Authenticate and receive access token</div>
            </div>

            <div class="endpoint">
                <span class="method method-get">GET</span>
                <span class="endpoint-path">/api/profile</span>
                <div class="endpoint-desc">Get authenticated user profile (requires token)</div>
            </div>

            <div class="endpoint">
                <span class="method method-get">GET</span>
                <span class="endpoint-path">/api/plans</span>
                <div class="endpoint-desc">List all available subscription plans</div>
            </div>

            <div class="endpoint">
                <span class="method method-post">POST</span>
                <span class="endpoint-path">/api/stripe/create-checkout</span>
                <div class="endpoint-desc">Create Stripe checkout session (requires token)</div>
            </div>

            <div class="endpoint">
                <span class="method method-post">POST</span>
                <span class="endpoint-path">/api/stripe/webhook</span>
                <div class="endpoint-desc">Stripe webhook endpoint for payment events</div>
            </div>
        </div>

        <div class="tech-stack">
            <span class="tech-badge">Laravel {{ Illuminate\Foundation\Application::VERSION }}</span>
            <span class="tech-badge">PHP {{ PHP_VERSION }}</span>
            <span class="tech-badge">Stripe API</span>
            <span class="tech-badge">MySQL</span>
            <span class="tech-badge">RESTful API</span>
        </div>
    </div>

    <footer>
        <p>&copy; {{ date('Y') }} Hydro AI. Powering the future of hydropower management.</p>
        <p style="margin-top: 0.5rem; font-size: 0.8rem;">
            Version 1.0.0 | Environment: {{ config('app.env') }}
        </p>
    </footer>
</body>
</html>