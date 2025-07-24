# ShutterPress

**A Shutterstock-style WordPress plugin for selling and distributing digital images with advanced subscription management.**

![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-5.0+-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.0+-blue.svg)
![License](https://img.shields.io/badge/license-GPL--2.0+-blue.svg)

---

## 🚀 Features

### 📸 **Digital Marketplace**
- Shutterstock-style image selling platform
- Watermarked preview images for browsing
- Original high-resolution files for download
- Multiple product types: Free, Subscription, Premium

### 🔐 **Secure Downloads**
- Cloud storage integration (Wasabi S3-compatible)
- Temporary signed URLs for secure access
- Direct downloads without exposing file URLs
- Automatic quota tracking and enforcement

### 💳 **Subscription Management**
- Custom subscription plans with download quotas
- Unlimited or limited download options
- User quota tracking and analytics
- Automatic quota resets and renewals

### 🎨 **Smart File Handling**
- Automatic watermarking for preview images
- Local storage for watermarked previews
- Cloud storage for original downloadable files
- Seamless integration with WordPress media library

### 🛒 **WooCommerce Integration**
- Native WooCommerce product management
- Custom product types for different access levels
- Integrated payment processing
- Order history and download tracking

---

## 📋 Requirements

- **WordPress**: 5.0 or higher
- **PHP**: 8.0 or higher
- **WooCommerce**: 4.0 or higher
- **Composer**: For dependency management
- **Wasabi Cloud Storage**: Account and credentials
- **MySQL**: 5.6 or higher

---

## 🔧 Installation

### 1. **Upload Plugin**
```bash
# Download and extract
unzip shutterpress.zip
upload to wp-content/plugins/shutterpress/
```

### 2. **Install Dependencies**
```bash
cd wp-content/plugins/shutterpress/
composer install --no-dev --optimize-autoloader
```

### 3. **Activate Plugin**
- Go to WordPress Admin → Plugins
- Activate "ShutterPress"

### 4. **Configure Credentials**
Add to your `wp-config.php`:
```php
// Wasabi Storage Credentials
define('DBI_AWS_ACCESS_KEY_ID', 'your_wasabi_access_key');
define('DBI_AWS_SECRET_ACCESS_KEY', 'your_wasabi_secret_key');
```

---

## ⚙️ Configuration

### 🗂️ **Wasabi Setup**
1. Create a [Wasabi](https://wasabi.com) account
2. Create a storage bucket (e.g., `your-site-media`)
3. Generate Access Keys in Wasabi Console
4. Add credentials to `wp-config.php` (see above)

### 🛍️ **Product Configuration**
1. **Create Products** in WooCommerce
2. **Set Product Type**: Virtual + Downloadable
3. **Add ShutterPress Type**:
   - `free` - Free downloads for logged-in users
   - `subscription` - Requires active subscription
   - `premium` - One-time purchase through WooCommerce

### 📊 **Subscription Plans**
1. Create WooCommerce products
2. Add `shutterpress-plan-product` tag
3. Configure quota limits in product meta

---

## 📖 Usage Guide

### 🖼️ **Adding Images**

#### **For Product Images (Previews)**
1. Upload images normally to WordPress
2. These stay **local** for watermarking
3. Set as product featured image

#### **For Downloadable Files**
1. Upload high-resolution images
2. Add to product as **downloadable files**
3. These automatically upload to **Wasabi cloud storage**
4. Users download originals without watermarks

### 👥 **Managing Users**

#### **Free Users**
- Can download products marked as `free`
- Must be logged in
- No quota restrictions

#### **Subscription Users**
- Purchase subscription plans
- Download quota tracked automatically
- Access to `subscription` type products
- Quotas reset based on plan duration

#### **Premium Users**
- Purchase individual images
- Immediate access after payment
- No quota restrictions per purchased item

### 📈 **Analytics & Tracking**
- Download logs in database
- User quota usage tracking
- Popular content analytics
- Revenue reporting through WooCommerce

---

## 🔧 Advanced Configuration

### 🎨 **Watermark Customization**
```php
// Customize watermark settings
add_filter('shutterpress_watermark_settings', function($settings) {
    $settings['opacity'] = 0.5;
    $settings['position'] = 'center';
    $settings['text'] = 'Your Site Name';
    return $settings;
});
```

### ☁️ **Cloud Storage Settings**
```php
// Customize Wasabi settings
add_filter('shutterpress_wasabi_settings', function($settings) {
    $settings['bucket'] = 'your-custom-bucket';
    $settings['region'] = 'us-east-1';
    return $settings;
});
```

### 📊 **Quota Management**
```php
// Custom quota rules
add_filter('shutterpress_quota_rules', function($rules) {
    $rules['daily_limit'] = 50;
    $rules['monthly_limit'] = 500;
    return $rules;
});
```

---

## 🗃️ Database Tables

ShutterPress creates these custom tables:

- `wp_shutterpress_user_quotas` - User subscription quotas
- `wp_shutterpress_download_logs` - Download tracking
- `wp_shutterpress_watermark_cache` - Watermark cache

---

## 🐛 Troubleshooting

### **Downloads Not Working**
1. Check Wasabi credentials in `wp-config.php`
2. Verify file uploaded to cloud storage
3. Check product has `_wasabi_object_key` meta
4. Enable debug logging: `define('WP_DEBUG_LOG', true);`

### **Files Not Uploading to Wasabi**
1. Ensure AWS SDK is installed: `composer install`
2. Check Wasabi bucket permissions
3. Verify network connectivity to Wasabi
4. Check PHP memory limits for large files

### **Watermarks Not Appearing**
1. Check GD extension installed: `extension=gd`
2. Verify write permissions on uploads folder
3. Check watermark image exists and is readable
4. Clear watermark cache

### **Quota Not Updating**
1. Check user has active subscription
2. Verify quota table exists and populated
3. Check subscription product has correct meta
4. Debug quota calculation in logs

---

## 🔒 Security Features

- **Secure Downloads**: No direct file access URLs
- **Time-limited URLs**: Automatic expiration (15 minutes default)
- **User Authentication**: Downloads require login
- **Quota Enforcement**: Prevents quota abuse
- **Input Validation**: All inputs sanitized and validated

---

## 🤝 Contributing

1. Fork the repository
2. Create feature branch: `git checkout -b feature/amazing-feature`
3. Commit changes: `git commit -m 'Add amazing feature'`
4. Push to branch: `git push origin feature/amazing-feature`
5. Open a Pull Request

---

## 📄 License

This project is licensed under the GPL-2.0+ License - see the [LICENSE](LICENSE) file for details.

---

## 🙋 Support

- **Documentation**: [Plugin Documentation](https://yoursite.com/docs)
- **Issues**: [GitHub Issues](https://github.com/AllenDev1/shutterpress)
- **Support Forum**: [WordPress.org Support](https://wordpress.org/support/plugin/shutterpress)
- **Email**: info@thebrilliantideas.com

---

## 🏆 Credits

- **AWS SDK**: For Wasabi cloud storage integration
- **WooCommerce**: E-commerce functionality
- **WordPress**: Foundation and ecosystem
- **Shutterstock**: Inspiration for marketplace design

---

## 📊 Changelog

### **1.0.0** - 2025-07-24
- Initial release
- Basic marketplace functionality
- Wasabi cloud storage integration
- Subscription management system
- Watermarking system
- Download tracking and quotas

---

**Made with ❤️ for the WordPress community**