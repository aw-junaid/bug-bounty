## Project Structure

```
sql-injection-lab/
├── backend/
│   ├── app.py
│   ├── requirements.txt
│   ├── config.py
│   └── init_db.py
├── frontend/
│   ├── index.html
│   ├── style.css
│   └── script.js
├── payload_detector/
│   ├── detector.py
│   └── payloads.json
└── README.md
```

# SQL Injection Learning Lab

## Prerequisites
- Kali Linux
- Python 3.8+
- MySQL Server
- pip3

## Installation

### 1. Install MySQL
```bash
sudo apt update
sudo apt install mysql-server
sudo systemctl start mysql
sudo mysql_secure_installation
```

### 2. Setup MySQL Database
```bash
sudo mysql -u root
```
```sql
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'your_password';
FLUSH PRIVILEGES;
EXIT;
```

### 3. Install Python Dependencies
```bash
cd sql-injection-lab/backend
pip3 install -r requirements.txt
```

### 4. Configure Database
Edit `backend/config.py` and set your MySQL password:
```python
DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': 'your_password',  # Change this
    'database': 'sql_injection_lab'
}
```

### 5. Initialize Database
```bash
python3 init_db.py
```

### 6. Start Backend Server
```bash
python3 app.py
```

### 7. Access Frontend
Open browser and navigate to:
```
http://localhost:5000/frontend/index.html
```

## Features

### 1. Secure Login
- Protected by SQL injection detection
- Shows blocked injection attempts
- Returns user data on successful login

### 2. User Registration
- Safe parameterized queries
- Input validation and sanitization
- SQL injection detection on all fields

### 3. SQL Injection Analyzer
- Real-time payload analysis
- Categorizes injection types
- Risk level assessment
- Detailed pattern matching

### 4. Vulnerable Demo
- Intentionally vulnerable endpoint
- Shows how SQL injection works
- Educational purposes only

## Test Payloads

### Authentication Bypass:
- `' OR '1'='1`
- `' OR 1=1 --`
- `admin' --`
- `' OR 'x'='x`

### Union Based:
- `' UNION SELECT NULL--`
- `' UNION SELECT username,password FROM users--`

### Time Based:
- `' OR SLEEP(5)--`
- `1' AND SLEEP(5)--`

### Stacked Queries:
- `'; DROP TABLE users; --`
- `'; DELETE FROM users WHERE '1'='1`

### Information Schema:
- `' UNION SELECT table_name FROM information_schema.tables--`

## API Endpoints

### POST /api/login
Secure login endpoint

### POST /api/login/vulnerable
Vulnerable login endpoint for learning

### POST /api/register
User registration

### POST /api/analyze
SQL injection analysis

### GET /api/users
Get all users (demo)

## Security Notes
- This is for educational purposes only
- Never use these techniques on production systems
- Always use parameterized queries
- Implement proper input validation
- Follow security best practices
```

## Running the Application

1. **Save all files** in the project structure shown above

2. **Start MySQL:**
```bash
sudo systemctl start mysql
```

3. **Initialize the database:**
```bash
cd sql-injection-lab/backend
python3 init_db.py
```

4. **Start the Flask backend:**
```bash
python3 app.py
```

5. **Access the frontend:**
Open `frontend/index.html` in browser or serve it:
```bash
cd frontend
python3 -m http.server 8000
```
Then visit `http://localhost:8000`

## Key Features Implemented:

1. **SQL Injection Detection**: The detector identifies multiple types of SQL injection including:
   - Authentication bypass
   - Union-based injection
   - Boolean-based blind injection
   - Time-based blind injection
   - Error-based injection
   - Stacked queries
   - Information schema access
   - Comment obfuscation
   - Function injection
   - Encoding evasion techniques

2. **Dual Endpoints**: 
   - Secure endpoint using parameterized queries
   - Vulnerable endpoint for learning purposes

3. **Real-time Analysis**: Analyzes any input for SQL injection patterns

4. **Comprehensive Dashboard**: Shows:
   - Detected injection types
   - Risk levels
   - Matched patterns
   - Executed queries (in vulnerable mode)

5. **Educational Payload Library**: Pre-built examples of common SQL injection techniques

This system provides a complete learning environment for understanding SQL injection attacks and defenses in a controlled, educational setting.
