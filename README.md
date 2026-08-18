# Shopee Live Inventory System

A comprehensive web-based inventory management system for Shopee sellers. This application integrates with the Shopee Partner API using OAuth 2.0 authentication, enabling sellers to manage their products, inventory, and business operations directly from a centralized dashboard.

## Overview

**Shopee Live Inventory System** is a full-featured inventory management platform built with PHP that empowers Shopee sellers to:

- 🔐 **Securely authenticate** with Shopee using Partner API OAuth 2.0
- 📊 **Manage inventory** across multiple Shopee shops
- 🛍️ **Track products** and their inventory levels
- 👥 **Manage multiple users** with role-based access control
- 👤 **Handle user profiles** and account management
- 📱 **Access via responsive dashboard** built with AdminLTE
- 💾 **Flexible database** support (MySQL or SQLite)

## Key Features

### Authentication & Security
- **Shopee Partner OAuth 2.0 Integration** - Securely connect with Shopee's partner platform
- **Session Management** - Secure user sessions with token-based authentication
- **Role-Based Access Control** - Different permission levels for admin, manager, and user roles
- **HMAC-SHA256 Signature Verification** - Secure API communication with Shopee

### Inventory Management
- **Product Management** - View and manage products synchronized with Shopee
- **Inventory Tracking** - Monitor stock levels and inventory updates
- **Multi-Shop Support** - Manage multiple Shopee shops from a single dashboard
- **Real-time Dashboard** - Centralized view of all inventory operations

### User Management
- **User Administration** - Create, update, and manage user accounts
- **Profile Management** - User profile customization and settings
- **Role Configuration** - Assign roles and permissions to users
- **Access Control** - Granular permission management

### Technology Stack
- **Backend:** PHP 7+ with PDO for database abstraction
- **Database:** MySQL or SQLite (flexible configuration)
- **Frontend:** AdminLTE 3.x - Professional responsive dashboard template
- **API Integration:** Shopee Partner API v2
- **Security:** HMAC-SHA256 authentication, prepared statements, session management

## Project Structure

```
shopee_live/
├── app/                          # Core application files
│   ├── config.php               # Database and Shopee credentials
│   ├── security.php             # Authentication and session management
│   ├── roles.php                # Role-based access control
│   ├── header.php               # Dashboard header
│   ├── footer.php               # Dashboard footer
│   ├── menu.php                 # Navigation menu
│   ├── content_holder.php       # Page content wrapper
│   ├── style.css                # Custom styling
│   └── index.php                # App entry point
├── dashboard/                    # Dashboard page
│   └── index.php                # Main dashboard
├── pages/                        # Application pages
│   ├── main.php                 # Main content page
│   ├── products.php             # Product management
│   ├── profile.php              # User profile page
│   └── users.php                # User administration
├── login/                        # Login page
│   └── index.php
├── logout/                       # Logout handler
│   └── index.php
├── success/                      # Success page
│   └── index.php
├── scripts/                      # Database and utility scripts
│   ├── create_tables.sql        # Database schema
│   ├── generate_users_table.php # User table initialization
│   └── generate_users_table_pdo.php
├── auth_live.php                # Shopee OAuth authentication entry point
├── callback.php                 # Shopee OAuth callback handler
├── index.php                    # Application router
└── README.md                    # This file
```

## Installation & Setup

### Prerequisites
- PHP 7.0 or higher
- MySQL 5.7+ or SQLite 3
- Shopee Partner credentials (Partner ID and Partner Key)
- A publicly accessible domain for OAuth callback URL

### Configuration

1. **Set up credentials** in `app/config.php`:
   ```php
   $partnerId = 'YOUR_PARTNER_ID';
   $partnerKey = 'YOUR_PARTNER_KEY';
   $redirectUrl = 'http://yourdomain.com/callback.php';
   ```

2. **Configure database** in `app/config.php`:
   ```php
   $db_host = 'localhost';
   $db_user = 'your_user';
   $db_pass = 'your_password';
   $db_name = 'shopee_live';
   ```

3. **Initialize the database**:
   ```bash
   # Using the provided SQL script
   mysql -u root -p shopee_live < scripts/create_tables.sql
   
   # Or run the PHP setup script
   php scripts/generate_users_table_pdo.php
   ```

### Running the Application

**Using PHP's Built-in Server:**
```bash
php -S 0.0.0.0:8000 -t .
```

Then open your browser and navigate to:
- Login: `http://localhost:8000/login/`
- Dashboard: `http://localhost:8000/dashboard/`
- OAuth Auth: `http://localhost:8000/auth_live.php`

**Using a Web Server (Nginx/Apache):**
- Point your web root to the `shopee_live` directory
- Ensure proper write permissions for the `data/` directory (if using SQLite)

### First Time Login

1. Visit the OAuth authentication page: `http://yourdomain.com/auth_live.php`
2. You'll be redirected to Shopee's authorization page
3. Authorize the application to access your Shopee shop
4. Shopee redirects back to the callback handler which stores your access token
5. Log in to the dashboard with your authorized account

## API Integration

### Shopee OAuth 2.0 Flow
The application implements the complete OAuth 2.0 authorization flow:

1. **Authorization Request** - Redirect to Shopee's authorization endpoint
2. **User Authorization** - Seller approves application access
3. **Authorization Code Exchange** - Exchange code for access and refresh tokens
4. **Token Storage** - Securely store tokens for API requests
5. **API Operations** - Use access token for subsequent API calls

### Debug Features
- `?debug=1` - Display OAuth signature details
- `?debug_exchange=1` - Show token exchange signature verification

## Security Features

- ✅ HMAC-SHA256 signature verification for all API requests
- ✅ Prepared statements to prevent SQL injection
- ✅ Session-based authentication with secure tokens
- ✅ Role-based access control (RBAC)
- ✅ Environment configuration separation
- ✅ Protected API endpoints requiring authentication

## Database Schema

The application uses the following main tables:
- `users` - User accounts and credentials
- `shops` - Shopee shop information and OAuth tokens
- `products` - Product catalog and inventory
- `roles` - User roles and permissions
- `permissions` - Access control definitions

See `scripts/create_tables.sql` for the complete schema.

## Development & Debugging

### Enable Debugging
Review the debug parameters in `auth_live.php` for OAuth signature verification:
- Check authentication request signatures
- Verify token exchange signatures
- Inspect raw Shopee API responses

### Database Support
The application gracefully switches between MySQL and SQLite:
- **MySQL** - Full-featured relational database (recommended for production)
- **SQLite** - Lightweight file-based database (suitable for development)

### Logging & Monitoring
Check the callback and authentication processes in:
- `callback.php` - OAuth callback handling
- `auth_live.php` - OAuth flow debugging
- `debug_signature.php` - Signature verification utilities

## Security Considerations

⚠️ **Important:**
- Keep `$partnerKey` and database credentials secure
- Use HTTPS in production
- Regularly update tokens and refresh credentials
- Implement rate limiting for API requests
- Audit user access and permissions regularly
- Never commit sensitive credentials to version control

## Support & Resources

- [Shopee Partner API Documentation](https://partner.shopeemobile.com/docs)
- [AdminLTE Documentation](https://adminlte.io)
- PHP PDO Documentation

## License

This project is provided for Shopee integration purposes. Ensure compliance with Shopee's Partner API terms of service.
