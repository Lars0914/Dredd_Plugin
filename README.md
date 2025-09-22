# DREDD AI - WordPress Plugin

**Version:** 1.0.0  
**Author:** DREDD AI Team  
**License:** GPL v2 or later  

## Overview

DREDD AI is a comprehensive WordPress plugin that provides brutal cryptocurrency analysis through a Judge Dredd-themed chatbot interface. The plugin integrates with local Ollama (Mixtral Dolphin) for uncensored AI responses and n8n workflows for comprehensive token analysis.

## Features

### ⚖️ Core Functionality
- **Judge Dredd Themed Chat Interface** - Responsive chat window with authentic Judge Dredd personality
- **Dual Analysis Modes** - Standard (family-friendly) and Psycho (uncensored) modes
- **Local AI Integration** - Ollama with Mixtral Dolphin model for complete control
- **Multi-API Analysis** - Integrates Grok, Dexscreener, security audits, and blockchain explorers
- **Smart Caching** - 24-hour analysis cache to optimize API usage
- **Auto Publishing** - Analysis results automatically published as WordPress posts

### 💳 Payment System
- **Credit-Based Tokens** - Flexible token packages with volume discounts
- **Stripe Integration** - Direct payment processing to admin's Stripe account
- **Crypto Payments** - USDT/USDC support with Web3 wallet integration
- **Multi-Chain Support** - Ethereum, BSC, Polygon, Arbitrum, PulseChain

### 🚀 Token Promotions
- **Sponsored Token Display** - Sidebar promotion system with click tracking
- **Admin Management** - Complete promotion approval and management system
- **Performance Analytics** - Click-through rates and ROI tracking
- **WordPress Integration** - Auto-generated promotional posts

### 👤 User Experience
- **User Dashboard** - Complete analysis history and credit management
- **Mobile Responsive** - Optimized for all devices
- **Real-time Updates** - Live chat with progress indicators
- **Data Export** - GDPR-compliant data export functionality

### 🛡️ Security & Compliance
- **Rate Limiting** - Protection against abuse
- **Input Validation** - Comprehensive security measures
- **GDPR Compliance** - Data export and deletion capabilities
- **Encrypted Storage** - Secure handling of sensitive data

## Installation

### Prerequisites
1. **WordPress 5.0+** with PHP 7.4+
2. **Ollama Server** running Mixtral Dolphin model
3. **n8n Instance** for workflow automation
4. **API Keys** for external services (Grok, Etherscan, etc.)

### Setup Steps

1. **Upload Plugin**
   ```bash
   # Upload the dredd-ai-plugin folder to /wp-content/plugins/
   # Or install via WordPress admin dashboard
   ```

2. **Activate Plugin**
   - Go to WordPress Admin → Plugins
   - Activate "DREDD AI - Cryptocurrency Analysis Tool"

3. **Configure Settings**
   - Navigate to DREDD AI → Settings
   - Configure Ollama URL (e.g., `http://localhost:11434` or ngrok tunnel)
   - Set n8n webhook URL
   - Add API keys for external services

4. **Payment Setup** (Optional)
   - Go to DREDD AI → Payments
   - Add your Stripe API keys
   - Configure crypto wallet addresses
   - Set token package pricing

5. **Add Chat Interface**
   ```php
   // Add to any page/post
   [dredd_chat]
   
   // Add user dashboard
   [dredd_user_dashboard]
   ```

## Configuration

### Ollama Integration
```php
// In WordPress admin
Ollama URL: http://localhost:11434  // or your ngrok tunnel
Model Name: mixtral-dolphin
API Timeout: 30 seconds
```

### n8n Workflow
```json
{
  "webhook_url": "http://localhost:5678/webhook/dredd-analysis",
  "callback_url": "https://yoursite.com/wp-admin/admin-ajax.php?action=dredd_analysis_complete"
}
```

### API Keys Required
- **Grok API** - For X/Twitter sentiment analysis
- **Etherscan API** - For Ethereum blockchain data
- **BSCScan API** - For BSC blockchain data
- **PolygonScan API** - For Polygon blockchain data
- **GoPlus Security API** - For smart contract audits

## Usage

### For Users
1. **Visit Chat Interface** - Use `[dredd_chat]` shortcode
2. **Select Mode** - Choose Standard or Psycho mode
3. **Enter Token Info** - Provide contract address and chain
4. **Get Analysis** - Receive DREDD's brutal verdict
5. **View History** - Access past analyses in user dashboard

### For Admins
1. **Monitor Analytics** - Real-time usage statistics
2. **Manage Payments** - Track revenue and transactions
3. **Control Features** - Toggle paid mode on/off
4. **Approve Promotions** - Manage sponsored token listings
5. **System Health** - Monitor Ollama and n8n connections

## API Endpoints

### AJAX Actions
- `dredd_chat` - Handle chat messages
- `dredd_analysis_complete` - Receive n8n analysis results
- `dredd_process_payment` - Process Stripe/crypto payments
- `dredd_get_user_data` - Retrieve user dashboard data
- `dredd_track_impression` - Track promotion views
- `dredd_track_click` - Track promotion clicks

### Webhook Endpoints
- `/wp-admin/admin-ajax.php?action=dredd_analysis_complete` - n8n callback
- `/wp-admin/admin-ajax.php?action=dredd_stripe_webhook` - Stripe webhooks

## Database Schema

### Custom Tables
- `wp_dredd_user_tokens` - User credit balances
- `wp_dredd_transactions` - Payment history
- `wp_dredd_analysis_history` - Analysis results
- `wp_dredd_promotions` - Token promotions
- `wp_dredd_cache` - Analysis cache
- `wp_dredd_user_sessions` - Chat sessions

## Customization

### Styling
```css
/* Override in your theme */
.dredd-chat-container {
    /* Custom chat styling */
}

.dredd-user-dashboard {
    /* Custom dashboard styling */
}
```

### Hooks & Filters
```php
// Customize DREDD responses
add_filter('dredd_ai_system_prompt', function($prompt) {
    return $prompt . ' Additional instructions...';
});

// Modify analysis data
add_filter('dredd_ai_analysis_data', function($data) {
    // Custom data processing
    return $data;
});
```

## Troubleshooting

### Common Issues

1. **Ollama Connection Failed**
   - Check if Ollama is running: `ollama serve`
   - Verify URL in settings (use ngrok for remote hosting)
   - Test connection in admin dashboard

2. **n8n Workflow Not Triggering**
   - Verify webhook URL is accessible
   - Check n8n workflow is active
   - Review error logs in WordPress

3. **Payment Issues**
   - Verify Stripe keys are correct
   - Check webhook endpoints are configured
   - Ensure SSL certificate is valid

4. **Chat Not Loading**
   - Check JavaScript console for errors
   - Verify AJAX endpoints are accessible
   - Confirm nonce validation

### Debug Mode
```php
// Enable debug logging
define('DREDD_AI_DEBUG', true);

// View logs
tail -f /wp-content/debug.log | grep DREDD
```

## Performance Optimization

### Caching
- Analysis results cached for 24 hours
- Database queries optimized with indexes
- Static assets minified and compressed

### Rate Limiting
- 60 requests/hour for logged-in users
- 20 requests/hour for anonymous users
- Configurable limits per user type

### Database Maintenance
- Automatic cleanup of expired data
- Scheduled optimization tasks
- Configurable retention periods

## Security Features

### Input Validation
- Contract address format validation
- SQL injection prevention
- XSS protection on all outputs
- CSRF protection with nonces

### Data Protection
- API key encryption
- Secure session management
- GDPR compliance tools
- User data export/deletion

## Development

### File Structure
```
dredd-ai-plugin/
├── dredd-ai.php              # Main plugin file
├── includes/                 # Core classes
│   ├── class-dredd-database.php
│   ├── class-dredd-ollama.php
│   ├── class-dredd-n8n.php
│   ├── class-dredd-payments.php
│   ├── class-dredd-crypto.php
│   ├── class-dredd-security.php
│   ├── class-dredd-promotions.php
│   └── class-dredd-analytics.php
├── admin/                    # Admin interface
│   └── class-dredd-admin.php
├── public/                   # Public interface
│   └── class-dredd-public.php
├── assets/                   # Static assets
│   ├── css/
│   ├── js/
│   └── images/
└── README.md
```

### Contributing
1. Fork the repository
2. Create feature branch
3. Follow WordPress coding standards
4. Test thoroughly
5. Submit pull request

## License

This plugin is licensed under the GPL v2 or later.

```
DREDD AI WordPress Plugin
Copyright (C) 2024 DREDD AI Team

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

## Support

For support, please contact the DREDD AI team or visit the plugin documentation.

### System Requirements
- WordPress 5.0+
- PHP 7.4+
- MySQL 5.6+
- SSL Certificate (for payments)
- Ollama Server with Mixtral Dolphin
- n8n Instance

### Recommended Hosting
- Memory: 256MB+ (512MB recommended)
- Disk Space: 100MB+
- Bandwidth: Unlimited
- SSL Support: Required

---

**I AM THE LAW! Justice never sleeps, and neither does DREDD AI.**
