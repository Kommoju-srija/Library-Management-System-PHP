# Library Management System (LMS) - PHP & MySQL

A simple, functional web-based application designed to manage a library's book inventory, registered users, and borrowing transactions. This project demonstrates core web development concepts using PHP for backend logic and MySQL for data persistence.

---

## ✨ Features

* **Full CRUD** (Create, Read, Update, Delete) operations for both Books and Users.
* **Dynamic Search & Filtering:** Allows users to search the book list by Title or Author.
* **Dynamic Sorting:** Sorts book lists by ID, Title, or Author (Ascending/Descending).
* **Relational Data Handling:** Manages borrowing records using a dedicated `borrowings` table with Foreign Keys.
* **Security:** Implements basic security measures like **Prepared Statements** to prevent SQL Injection and **`htmlspecialchars()`** to prevent XSS.

---

## 🛠️ Technology Stack

* **Backend Logic:** PHP (Tested with PHP 8.x)
* **Database:** MySQL
* **Frontend:** HTML5, CSS3
* **Local Environment:** XAMPP or WAMP server

---

## 🚀 Installation and Setup

Follow these steps to get a local copy of the project running.

### 1. Database Setup

1.  Start your Apache and MySQL servers via the XAMPP/WAMP Control Panel.
2.  Navigate to **phpMyAdmin** (`http://localhost/phpmyadmin/`).
3.  Create a new database named **`library_db`**.
4.  Execute the following SQL commands in the SQL tab to create the necessary tables:

    ```sql
    CREATE TABLE books (
        id INT(11) PRIMARY KEY AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        author VARCHAR(255) NOT NULL,
        genre VARCHAR(100),
        year YEAR(4)
    );

    CREATE TABLE users (
        id INT(11) PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL
    );

    CREATE TABLE borrowings (
        id INT(11) PRIMARY KEY AUTO_INCREMENT,
        book_id INT(11) NOT NULL,
        user_id INT(11) NOT NULL,
        borrow_date DATE NOT NULL,
        return_date DATE NULL,
        FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    );
    ```

### 2. Project File Setup

1.  Clone this repository or download the source code files.
2.  Place the project files (e.g., `index.php` or `library_db.php`) into your XAMPP's `htdocs` directory (e.g., in a folder named `lms`).
3.  **Crucially, ensure the database connection details in your PHP file are correct:**

    ```php
    $conn = mysqli_connect("localhost", "root", "", "library_db");
    ```
    *(Adjust hostname, username, and password if different from the default XAMPP settings.)*

### 3. Access the Application

Open your web browser and navigate to:
