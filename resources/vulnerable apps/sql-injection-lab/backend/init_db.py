import mysql.connector
from config import DB_CONFIG
import hashlib
from datetime import datetime

def init_database():
    # Connect without database first
    conn = mysql.connector.connect(
        host=DB_CONFIG['host'],
        user=DB_CONFIG['user'],
        password=DB_CONFIG['password']
    )
    cursor = conn.cursor()
    
    # Create database
    cursor.execute("CREATE DATABASE IF NOT EXISTS sql_injection_lab")
    cursor.execute("USE sql_injection_lab")
    
    # Create users table
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL,
            password VARCHAR(255) NOT NULL,
            age INT,
            date_of_join DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    """)
    
    # Insert dummy data
    dummy_users = [
        ('admin', 'admin123!', 30, '2023-01-15'),
        ('john_doe', 'password123', 25, '2023-06-20'),
        ('jane_smith', 'securepass', 28, '2023-03-10'),
        ('bob_wilson', 'bob12345', 35, '2022-11-05'),
        ('alice_admin', 'alice@2023', 32, '2023-08-01'),
        ('guest_user', 'guest2024', 22, '2024-01-10'),
        ('developer', 'dev@2024', 27, '2023-09-15'),
        ('test_user', 'test1234', 29, '2023-04-22'),
        ('security', 'sec!2024', 31, '2023-12-01'),
        ('manager', 'mgr@2023', 40, '2022-06-30')
    ]
    
    for user in dummy_users:
        # Using parameterized query to safely insert data
        cursor.execute(
            "INSERT INTO users (username, password, age, date_of_join) VALUES (%s, %s, %s, %s)",
            user
        )
    
    conn.commit()
    cursor.close()
    conn.close()
    print("Database initialized successfully with dummy data!")

if __name__ == "__main__":
    init_database()
