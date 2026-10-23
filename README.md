# Trello - Task Management Application

## Description

Trello is a web application developed with PHP that allows users to organize boards, lists, and cards to manage their tasks and activities.

The application includes user authentication, board management, task organization, member management, and activity tracking.

## Features

### Authentication
- User registration
- User login and logout
- Session management

### Board Management
- Create new boards
- Edit board information
- Delete boards
- View available boards

### List and Card Management
- Add and delete lists
- Add, edit, and delete cards
- View card details
- Move cards between lists

### Member Management
- Add members to boards
- Manage board members

### Notifications and Activity Tracking
- Track user activities
- Manage notifications
- Mark notifications as read
- Dismiss alerts

## Technologies Used

- **PHP** — Server-side logic and application functionality
- **HTML5** — Page structure
- **CSS3** — User interface styling
- **JavaScript** — Client-side interactions, if implemented
- **SQL** — Database schema and queries
- **MySQL** — Database management, if configured in the project

## Project Structure

```text
Trello/
├── dashboard/
├── .gitignore
├── activity_log.php
├── add_card.php
├── add_list.php
├── add_member.php
├── applications.html
├── board.php
├── card_details.php
├── create_board.php
├── dashboard.css
├── dashboard.php
├── db.php
├── delete_board.php
├── delete_card.php
├── delete_list.php
├── dismiss_alert.php
├── edit_board.php
├── edit_card.php
├── footer.php
├── header.php
├── index.php
├── login_process.php
├── logout.php
├── manage_member.php
├── mark_notification_read.php
├── move_card.php
├── register_process.php
├── register.php
└── schema.sql
```

## 🚀 Installation and Setup

### Prerequisites

- PHP
- A local server such as XAMPP
- MySQL or MariaDB, if used by the application
- A web browser

### Steps

1. Clone or download this repository.
2. Place the project folder inside the `htdocs` directory of XAMPP.
3. Start Apache and MySQL from the XAMPP Control Panel.
4. Create the database required by the application.
5. Import `schema.sql` into the database using phpMyAdmin or the MySQL command line.
6. Configure the database connection in `db.php`.
7. Open the application in your browser:

   `http://localhost/Trello/`

8. Register an account and log in to use the application.

## 🗄️ Database Configuration

The file `schema.sql` contains the database schema used to create the required tables.

The file `db.php` handles the database connection.

Before running the application, make sure the database name, username, password, and connection settings match your local environment.

**Security note:** Never publish real database passwords or sensitive credentials in this repository.

## Security

- Protect sensitive configuration files.
- Use secure password hashing and verification.
- Use prepared statements for database queries.
- Validate user input on the server side.
- Verify user permissions before allowing modifications or deletions.

## Project Objective

This project aims to practice PHP web development and database integration by building a task management application inspired by Trello.

## Author

Developed as a personal web development project.

## 📄 License

This project is intended for educational and personal use.
