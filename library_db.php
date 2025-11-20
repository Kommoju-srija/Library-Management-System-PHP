<?php
// Database connection
$conn = mysqli_connect("localhost", "root", "", "library_db");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Status message variable
$message = '';
$message_type = ''; // 'success' or 'error'

// --- Data Sanitization Helper ---
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Helper function for Prepared Statements
function execute_stmt($conn, $sql, $types, $params) {
    global $message, $message_type;
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        $bind_params = array_merge([$stmt, $types], $params);
        call_user_func_array('mysqli_stmt_bind_param', $bind_params);

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return true;
        } else {
            if (mysqli_errno($conn) == 1062) {
                 $message = "Error: Duplicate entry. This data might already exist.";
            } else {
                 $message = "Error executing statement: " . mysqli_stmt_error($stmt);
            }
            $message_type = 'error';
            mysqli_stmt_close($stmt);
            return false;
        }
    } else {
        $message = "Error preparing statement: " . mysqli_error($conn);
        $message_type = 'error';
        return false;
    }
}

// Helper function for SELECT statements
function fetch_data($conn, $sql) {
    $result = mysqli_query($conn, $sql);
    $data = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        mysqli_free_result($result);
    }
    return $data;
}

// --- CRUD Operations ---

// Add Book
if(isset($_POST['add_book'])){
    $title = sanitize_input($_POST['title']);
    $author = sanitize_input($_POST['author']);
    $genre = sanitize_input($_POST['genre']);
    $year = (int)$_POST['year'];
    $user_id = (int)$_POST['user_id_to_assign']; 

    $current_year = date('Y');
    if ($year > $current_year || $year < 1900) {
        $message = "Error: Invalid Year. Year must be between 1900 and {$current_year}.";
        $message_type = 'error';
    } elseif (empty($title) || empty($author)) {
         $message = "Error: Title and Author cannot be empty.";
         $message_type = 'error';
    } else {
        $sql_book = "INSERT INTO books(title, author, genre, year) VALUES(?, ?, ?, ?)";
        if (execute_stmt($conn, $sql_book, "sssi", [&$title, &$author, &$genre, &$year])) {
            
            $book_id = mysqli_insert_id($conn); 
            $message = "Book '{$title}' added successfully!";
            $message_type = 'success';

            // Process Chaining
            if ($user_id > 0) {
                $borrow_date = date('Y-m-d');
                $user_check = fetch_data($conn, "SELECT id FROM users WHERE id = {$user_id}");

                if (!empty($user_check)) {
                    $sql_borrow = "INSERT INTO borrowings(book_id, user_id, borrow_date) VALUES(?, ?, ?)";
                    if (execute_stmt($conn, $sql_borrow, "iis", [&$book_id, &$user_id, &$borrow_date])) {
                        $message .= " **(Chaining Success:** Book automatically assigned to User ID: {$user_id}.)";
                    }
                } else {
                     $message .= " **(Chaining Warning:** Book added, but User ID: {$user_id} does not exist for assignment.)";
                     $message_type = 'error';
                }
            }
        } else {
             $message = "Error adding book: " . mysqli_error($conn);
             $message_type = 'error';
        }
    }
}

// Edit Book
if(isset($_POST['edit_book'])){
    $id = (int)$_POST['id'];
    $title = sanitize_input($_POST['title']);
    $author = sanitize_input($_POST['author']);
    $genre = sanitize_input($_POST['genre']);
    $year = (int)$_POST['year'];

    $current_year = date('Y');
    if ($year > $current_year || $year < 1900) {
        $message = "Error: Invalid Year. Year must be between 1900 and {$current_year}.";
        $message_type = 'error';
    } elseif (empty($title) || empty($author)) {
         $message = "Error: Title and Author cannot be empty.";
         $message_type = 'error';
    } else {
        $sql = "UPDATE books SET title = ?, author = ?, genre = ?, year = ? WHERE id = ?";
        if (execute_stmt($conn, $sql, "sssii", [&$title, &$author, &$genre, &$year, &$id])) {
            $message = "Book ID: {$id} updated successfully!";
            $message_type = 'success';
        }
    }
}

// Delete Book (Unchanged)
if(isset($_GET['delete_book'])){
    $id = (int)$_GET['delete_book'];
    execute_stmt($conn, "DELETE FROM borrowings WHERE book_id = ?", "i", [&$id]); 
    if (execute_stmt($conn, "DELETE FROM books WHERE id = ?", "i", [&$id])) {
        $message = "Book ID: {$id} deleted successfully!";
        $message_type = 'success';
    }
}

// Add User
if(isset($_POST['add_user'])){
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Error: Invalid email format.";
        $message_type = 'error';
    } elseif (empty($name)) {
        $message = "Error: Name cannot be empty.";
        $message_type = 'error';
    } else {
        $duplicate_check = fetch_data($conn, "SELECT id FROM users WHERE email = '{$email}'");
        
        if (!empty($duplicate_check)) {
            $message = "Error: The email '{$email}' is already registered.";
            $message_type = 'error';
        } else {
            $sql = "INSERT INTO users(name, email) VALUES(?, ?)";
            if (execute_stmt($conn, $sql, "ss", [&$name, &$email])) {
                $message = "User '{$name}' added successfully!";
                $message_type = 'success';
            }
        }
    }
}

// Edit User
if(isset($_POST['edit_user'])){
    $id = (int)$_POST['id'];
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Error: Invalid email format.";
        $message_type = 'error';
    } elseif (empty($name)) {
        $message = "Error: Name cannot be empty.";
        $message_type = 'error';
    } else {
        $duplicate_check = fetch_data($conn, "SELECT id FROM users WHERE email = '{$email}' AND id != {$id}");
        if (!empty($duplicate_check)) {
            $message = "Error: The email '{$email}' is already registered to another user.";
            $message_type = 'error';
        } else {
            $sql = "UPDATE users SET name = ?, email = ? WHERE id = ?";
            if (execute_stmt($conn, $sql, "ssi", [&$name, &$email, &$id])) {
                $message = "User ID: {$id} updated successfully!";
                $message_type = 'success';
            }
        }
    }
}

// Delete User (Unchanged)
if(isset($_GET['delete_user'])){
    $id = (int)$_GET['delete_user'];
    execute_stmt($conn, "DELETE FROM borrowings WHERE user_id = ?", "i", [&$id]); 
    if (execute_stmt($conn, "DELETE FROM users WHERE id = ?", "i", [&$id])) {
        $message = "User ID: {$id} deleted successfully!";
        $message_type = 'success';
    }
}


// --- Data Fetching, Search, and Sorting Logic ---
$search_term = '';
$search_where = '';
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_term_safe = mysqli_real_escape_string($conn, sanitize_input($_GET['search']));
    $search_term = $search_term_safe; 
    $search_where = " WHERE title LIKE '%{$search_term_safe}%' OR author LIKE '%{$search_term_safe}%'";
}

// Sorting Logic
$sort_column = 'id';
$sort_order = 'DESC';

if (isset($_GET['sort'])) {
    $allowed_columns = ['id', 'title', 'author'];
    $allowed_orders = ['ASC', 'DESC'];
    
    $requested_column = sanitize_input($_GET['sort']);
    $requested_order = isset($_GET['order']) ? strtoupper(sanitize_input($_GET['order'])) : 'ASC';

    if (in_array($requested_column, $allowed_columns)) {
        $sort_column = $requested_column;
    }
    if (in_array($requested_order, $allowed_orders)) {
        $sort_order = $requested_order;
    }
}

// Fetch books data with search filter and sorting
$books_data = fetch_data($conn, "SELECT * FROM books {$search_where} ORDER BY {$sort_column} {$sort_order}");

// Fetch total counts
$total_books_count = fetch_data($conn, "SELECT COUNT(id) AS count FROM books")[0]['count'];
$filtered_books_count = count($books_data);
$total_users_count = fetch_data($conn, "SELECT COUNT(id) AS count FROM users")[0]['count'];

// Fetch users data
$users_data = fetch_data($conn, "SELECT * FROM users ORDER BY id DESC");


// Check for edit mode
$edit_book = null;
if(isset($_GET['edit_book_id'])){
    $id = (int)$_GET['edit_book_id'];
    $book = fetch_data($conn, "SELECT * FROM books WHERE id = {$id}");
    if (!empty($book)) {$edit_book = $book[0];}
}
$edit_user = null;
if(isset($_GET['edit_user_id'])){
    $id = (int)$_GET['edit_user_id'];
    $user = fetch_data($conn, "SELECT * FROM users WHERE id = {$id}");
    if (!empty($user)) {$edit_user = $user[0];}
}

// Function to generate the next sort order for a column link
function get_next_order($current_col, $current_order, $target_col) {
    if ($current_col === $target_col) {
        return $current_order === 'ASC' ? 'DESC' : 'ASC';
    }
    return 'ASC'; 
}

// Function to display the sort indicator
function get_sort_indicator($current_col, $current_order, $target_col) {
    if ($current_col === $target_col) {
        return $current_order === 'ASC' ? ' ▲' : ' ▼';
    }
    return '';
}

// --- NEW HELPER FUNCTION TO RETAIN QUERY PARAMETERS (FIX) ---
function get_current_query_params($exclude = []) {
    $params = $_GET;
    // Remove parameters that shouldn't be retained (like the edit IDs)
    foreach ($exclude as $key) {
        unset($params[$key]);
    }
    // Build query string, prefixing with '&' if it's not the first parameter
    $query = http_build_query($params);
    return $query ? '&' . $query : '';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Library Management</title>
<style>
    /* CSS updated for Quick Links */
    body { background: linear-gradient(120deg,#89f7fe,#66a6ff); font-family: 'Segoe UI',sans-serif; margin:0; padding:0; animation: bgAnim 10s infinite alternate; }
    @keyframes bgAnim { 0%{background:linear-gradient(120deg,#89f7fe,#66a6ff);} 100%{background:linear-gradient(120deg,#fbc2eb,#a6c1ee);} }
    h2 { color:#fff; text-align:center; margin-top:30px; text-shadow:2px 2px 3px #0007; animation: headAnim 2s 1; }
    @keyframes headAnim { 0%{transform:scale(0.8);opacity:0;} 100%{transform:scale(1);opacity:1;} }
    .nav-links { text-align: center; margin-bottom: 20px; } 
    .nav-links a { 
        display: inline-block; 
        padding: 10px 20px; 
        margin: 0 10px; 
        background: #fff; 
        color: #66a6ff; 
        text-decoration: none; 
        border-radius: 20px; 
        font-weight: bold;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        transition: background 0.3s;
    }
    .nav-links a:hover { background: #66a6ff; color: #fff; }

    .main-container { display:flex; justify-content:space-around; margin:40px 0; flex-wrap: wrap; }
    .card { background:#fff; box-shadow:0 4px 14px #0002; border-radius:10px; padding:30px; width:450px; margin: 15px; animation: fadeIn 1.1s; }
    @keyframes fadeIn { 0%{opacity:0;transform:translateY(30px);} 100%{opacity:1;transform:translateY(0);} }
    label{display:block;margin-top:15px;}
    input[type="text"], input[type="number"], input[type="email"] { width:100%;padding:8px; border-radius:5px; border:1px solid #88f; margin-top:5px; box-sizing: border-box; }
    .search-box { display: flex; margin-bottom: 20px; }
    .search-box input { flex-grow: 1; margin-right: 10px; }
    .search-box button { width: 100px; margin-top: 5px; } 
    .btn { margin-top:20px; background:#89f7fe; border:none; padding:12px 20px; border-radius:5px; color:#333; cursor:pointer; font-weight:bolder; transition:background .3s; box-shadow: 0 2px 8px #a6c1ee99; animation: bounceBtn 1s infinite alternate; }
    .btn.edit-btn { background: #ffcc00; }
    .btn.edit-btn:hover { background: #e0b300; }
    .btn:hover { background:#66a6ff; }
    @keyframes bounceBtn {100%{transform:translateY(-5px);}}
    table { border-collapse:collapse; width:100%; margin-top:30px; animation: fadeIn 2.5s; }
    th, td { border:1px solid #a6c1ee; padding:8px 12px; text-align:left; }
    th { background:#a6c1ee; color:#fff; }
    tr:nth-child(even) { background: #fbc2eb; }
    a.del { color:red;text-decoration:none;font-weight:bold; font-size:18px; transition:color .2s; }
    a.del:hover { color:#a60064; }
    a.edit { color:blue;text-decoration:none;font-weight:bold; font-size:18px; margin-left: 10px; transition:color .2s; }
    a.edit:hover { color:#0056b3; }
    .message { padding: 15px; margin: 20px auto; border-radius: 5px; width: 80%; text-align: center; font-weight: bold; box-shadow: 0 4px 8px rgba(0,0,0,0.1); animation: messageAnim 0.5s ease-out; }
    .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    .count-status { padding: 10px 0; text-align: center; font-weight: bold; color: #444; background: #eef; border-radius: 5px; margin-bottom: 10px; }
    th a { color: white; text-decoration: none; display: block; }
</style>
</head>
<body>
<h2>🔗 Library Management System (With Sorting & Security)</h2>

<div class="nav-links">
    <a href="#add-edit-forms">➕ Add/Edit</a>
    <a href="#books-list">📚 Books List</a>
    <a href="#users-list">👥 Users List</a>
</div>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div class="main-container" id="add-edit-forms">
    <div class="card">
        <h3><?php echo $edit_book ? 'Edit Book' : 'Add Book (with Chaining)'; ?></h3>
        <form method="post">
            <?php if($edit_book): ?>
                <input type="hidden" name="id" value="<?php echo $edit_book['id']; ?>">
            <?php endif; ?>
            
            <label>Title:</label>
            <input type="text" name="title" value="<?php echo $edit_book ? htmlspecialchars($edit_book['title']) : ''; ?>" required>
            <label>Author:</label>
            <input type="text" name="author" value="<?php echo $edit_book ? htmlspecialchars($edit_book['author']) : ''; ?>" required>
            <label>Genre:</label>
            <input type="text" name="genre" value="<?php echo $edit_book ? htmlspecialchars($edit_book['genre']) : ''; ?>">
            <label>Year (1900-<?php echo date('Y'); ?>):</label>
            <input type="number" name="year" value="<?php echo $edit_book ? htmlspecialchars($edit_book['year']) : ''; ?>" min="1900" max="<?php echo date('Y'); ?>">
            
            <?php if(!$edit_book): ?>
                <label>Assign to User ID (Optional for Chaining):</label>
                <input type="number" name="user_id_to_assign" placeholder="Enter User ID to assign immediately">
            <?php endif; ?>

            <button class="btn <?php echo $edit_book ? 'edit-btn' : ''; ?>" name="<?php echo $edit_book ? 'edit_book' : 'add_book'; ?>">
                <?php echo $edit_book ? 'Update Book' : 'Add Book & Chain'; ?>
            </button>
            <?php if($edit_book): ?>
                <a href="index.php?<?php echo get_current_query_params(['edit_book_id', 'edit_user_id']); ?>" class="btn" style="float:right;">Cancel Edit</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <h3><?php echo $edit_user ? 'Edit User' : 'Add User'; ?></h3>
        <form method="post">
            <?php if($edit_user): ?>
                <input type="hidden" name="id" value="<?php echo $edit_user['id']; ?>">
            <?php endif; ?>
            
            <label>Name:</label>
            <input type="text" name="name" value="<?php echo $edit_user ? htmlspecialchars($edit_user['name']) : ''; ?>" required>
            <label>Email:</label>
            <input type="email" name="email" value="<?php echo $edit_user ? htmlspecialchars($edit_user['email']) : ''; ?>" required>
            
            <button class="btn <?php echo $edit_user ? 'edit-btn' : ''; ?>" name="<?php echo $edit_user ? 'edit_user' : 'add_user'; ?>">
                <?php echo $edit_user ? 'Update User' : 'Add User'; ?>
            </button>
            <?php if($edit_user): ?>
                 <a href="index.php?<?php echo get_current_query_params(['edit_book_id', 'edit_user_id']); ?>" class="btn" style="float:right;">Cancel Edit</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="main-container">
    <div class="card" style="width: 600px;" id="books-list">
        <h3>📚 Books List</h3>
        
        <div class="count-status">
            <?php if (!empty($search_term)): ?>
                Found **<?php echo $filtered_books_count; ?>** books matching "<?php echo htmlspecialchars($search_term); ?>" (Total: <?php echo $total_books_count; ?>)
            <?php else: ?>
                Total Books in Library: **<?php echo $total_books_count; ?>**
            <?php endif; ?>
        </div>
        
        <form method="get" class="search-box">
            <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort_column); ?>">
            <input type="hidden" name="order" value="<?php echo htmlspecialchars($sort_order); ?>">
            
            <input type="text" name="search" placeholder="Search by Title or Author..." value="<?php echo htmlspecialchars($search_term); ?>">
            <button class="btn" type="submit" style="margin-top: 5px;">Search</button>
        </form>
        
        <table>
            <tr>
                <th>
                    <a href="?sort=id&order=<?php echo get_next_order($sort_column, $sort_order, 'id'); ?><?php echo !empty($search_term) ? "&search={$search_term}" : ''; ?>">
                        ID<?php echo get_sort_indicator($sort_column, $sort_order, 'id'); ?>
                    </a>
                </th>
                <th>
                    <a href="?sort=title&order=<?php echo get_next_order($sort_column, $sort_order, 'title'); ?><?php echo !empty($search_term) ? "&search={$search_term}" : ''; ?>">
                        Title<?php echo get_sort_indicator($sort_column, $sort_order, 'title'); ?>
                    </a>
                </th>
                <th>
                    <a href="?sort=author&order=<?php echo get_next_order($sort_column, $sort_order, 'author'); ?><?php echo !empty($search_term) ? "&search={$search_term}" : ''; ?>">
                        Author<?php echo get_sort_indicator($sort_column, $sort_order, 'author'); ?>
                    </a>
                </th>
                <th>Genre</th>
                <th>Year</th>
                <th>Actions</th>
            </tr>
            <?php if (!empty($books_data)): ?>
                <?php foreach($books_data as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['id']); ?></td>
                        <td><?php echo htmlspecialchars($row['title']); ?></td>
                        <td><?php echo htmlspecialchars($row['author']); ?></td>
                        <td><?php echo htmlspecialchars($row['genre']); ?></td>
                        <td><?php echo htmlspecialchars($row['year']); ?></td>
                        <td>
                            <a class='edit' href='?edit_book_id=<?php echo $row['id']; ?><?php echo get_current_query_params(['edit_book_id', 'edit_user_id']); ?>'>✍️</a>
                            <a class='del' href='?delete_book=<?php echo $row['id']; ?>' onclick="return confirm('Deleting book will also delete related borrowings. Are you sure?');">🗑️</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6" style="text-align: center; color: #777; font-weight: bold; padding: 20px;">
                    <?php echo !empty($search_term) ? "No books found matching '{$search_term}'." : "The library is empty. Please add a book!"; ?>
                </td></tr>
            <?php endif; ?>
        </table>
    </div>

    <div class="card" style="width: 400px;" id="users-list">
        <h3>👥 Users List</h3>
        
        <div class="count-status">
            Total Users Registered: **<?php echo $total_users_count; ?>**
        </div>
        
        <table>
            <tr><th>ID</th><th>Name</th><th>Email</th><th>Actions</th></tr>
            <?php if (!empty($users_data)): ?>
                <?php foreach($users_data as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['id']); ?></td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td>
                            <a class='edit' href='?edit_user_id=<?php echo $row['id']; ?><?php echo get_current_query_params(['edit_book_id', 'edit_user_id']); ?>'>✍️</a>
                            <a class='del' href='?delete_user=<?php echo $row['id']; ?>' onclick="return confirm('Deleting user will also delete related borrowings. Are you sure?');">🗑️</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="4" style="text-align: center; color: #777; font-weight: bold; padding: 20px;">No users registered yet. Please add a user!</td></tr>
            <?php endif; ?>
        </table>
    </div>
</div>

</body>
</html>