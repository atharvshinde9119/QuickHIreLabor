# 🚀 QuickHire Labor Application Setup Guide

## 📋 Prerequisites
- 🔧 XAMPP with PHP 7.4+ and MySQL 5.7+
- 🌐 Web browser
- 📦 Git (optional)

## ⚙️ Installation Steps

1. **💾 Setup Database**
   - 🟢 Start XAMPP Control Panel
   - 🔄 Start Apache and MySQL services
   - 🗄️ Open phpMyAdmin (http://localhost/phpmyadmin)
   - 📝 Create a new database named `quickhire`

2. **🛠️ Configure Application**
   - 📂 Copy all project files to `c:\xampp\htdocs\QuickHireLabor2\`
   - ⚡ Update database configuration in `config.php`:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     define('DB_NAME', '');
  
     ```

3. **📁 Database Structure**

   -- Default admin user
   INSERT INTO users (first_name, last_name, email, phone, password, role) 
   VALUES ('Admin', 'User', 'admin@quickhirelabor.com', '1234567890', 
   '$2y$10$YourHashedPasswordHere', 'admin');

   -- Sample users for testing
   INSERT INTO users (first_name, last_name, email, phone, password, role) VALUES
   ('John', 'Customer', 'customer@test.com', '9876543210', '$2y$10$Hash1', 'customer'),
   ('Jane', 'Laborer', 'laborer@test.com', '8765432109', '$2y$10$Hash2', 'laborer');
   ```

4. **🔑 Default User Accounts**
   
   **👨‍💼 Admin Account**
   - 📧 Email: admin@quickhirelabor.com
   - 🔒 Password: admin123

   **👥 Test Accounts**
   - 🏠 Customer
     - 📧 Email: customer@example.com
     - 🔒 Password: password123
   
   - 👷 Laborer
     - 📧 Email: laborer@example.com
     - 🔒 Password: password123

5. **📁 File Structure**
```
QuickHireLabor2/
├── 📝 includes/
│   ├── config.php    # Configuration
│   ├── dbconn.php    # Database connection
│   └── header.php    # Common header
├── 📊 css/           # Stylesheets
├── 🖼️ images/        # Static images
├── 📝 faq.php        # FAQ page
└── 📝 signup.php     # Registration page
```

6. **🧪 Testing the Installation**
   - 🌐 Visit http://localhost/QuickHireLabor2/
   - 🔑 Test admin login
   - 👥 Create test accounts
   - ✅ Test core features

## ❗ Troubleshooting

1. **🔌 Database Connection Issues**
   - ✔️ Check XAMPP services
   - 🔍 Verify database credentials
   - ✅ Confirm database exists

2. **📂 Upload Permissions**
   - 🔒 Fix folder permissions:
     ```bash
     chmod 777 uploads/profile_pics
     chmod 777 uploads/job_images
     ```

3. **⚠️ Common Errors**
   - 📋 "Table not found" → Re-run sql_setup.php
   - 📁 "Cannot write file" → Check permissions
   - 🔌 "Connection failed" → Check config




# Database Migrations

This directory is used for CSV files containing data to be imported into the database tables.

## How to Use

1. Create a CSV file with the exact name of the table you want to import data into (e.g., `services.csv` for the `services` table)
2. The first row of the CSV must contain the column names exactly as they appear in the database
3. Subsequent rows should contain the data to be imported
4. Place the CSV file in this directory
5. Run the `sql_setup.php` script to import the data

## Example

For the `services` table, create a file named `services.csv` with contents like:

