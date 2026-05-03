from flask import Flask, request, jsonify
from flask_cors import CORS
import mysql.connector
from config import DB_CONFIG
import sys
sys.path.append('../payload_detector')
from detector import SQLInjectionDetector

app = Flask(__name__)
CORS(app)
detector = SQLInjectionDetector()

def get_db_connection():
    return mysql.connector.connect(**DB_CONFIG)

@app.route('/api/login', methods=['POST'])
def login():
    data = request.json
    username = data.get('username', '')
    password = data.get('password', '')
    
    # Check for SQL injection
    injection_result = detector.detect_injection(username)
    password_injection = detector.detect_injection(password)
    
    # If any field has SQL injection
    if injection_result['has_injection'] or password_injection['has_injection']:
        all_injections = []
        if injection_result['has_injection']:
            all_injections.extend(injection_result['injections_found'])
        if password_injection['has_injection']:
            all_injections.extend(password_injection['injections_found'])
            
        return jsonify({
            'status': 'blocked',
            'message': 'SQL Injection detected!',
            'injections': all_injections,
            'analysis': {
                'username_analysis': injection_result,
                'password_analysis': password_injection
            }
        }), 403
    
    # Safe query using parameterized queries (vulnerable version for learning)
    # NORMAL LOGIN (Safe)
    try:
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        
        # Secure parameterized query
        cursor.execute(
            "SELECT * FROM users WHERE username = %s AND password = %s",
            (username, password)
        )
        user = cursor.fetchone()
        
        cursor.close()
        conn.close()
        
        if user:
            # Remove password from response
            user.pop('password', None)
            return jsonify({
                'status': 'success',
                'message': 'Login successful',
                'user': user
            }), 200
        else:
            return jsonify({
                'status': 'error',
                'message': 'Invalid credentials'
            }), 401
            
    except Exception as e:
        return jsonify({
            'status': 'error',
            'message': f'Database error: {str(e)}'
        }), 500

@app.route('/api/login/vulnerable', methods=['POST'])
def login_vulnerable():
    """Vulnerable endpoint for learning purposes"""
    data = request.json
    username = data.get('username', '')
    password = data.get('password', '')
    
    # Check for SQL injection first
    injection_result = detector.detect_injection(username)
    password_injection = detector.detect_injection(password)
    
    if injection_result['has_injection'] or password_injection['has_injection']:
        # Show what injection was detected but still allow the query for learning
        all_injections = []
        if injection_result['has_injection']:
            all_injections.extend(injection_result['injections_found'])
        if password_injection['has_injection']:
            all_injections.extend(password_injection['injections_found'])
        
        # VULNERABLE QUERY - SQL Injection possible here
        try:
            conn = get_db_connection()
            cursor = conn.cursor(dictionary=True)
            
            # Intentionally vulnerable query for learning
            query = f"SELECT * FROM users WHERE username = '{username}' AND password = '{password}'"
            print(f"Executing vulnerable query: {query}")
            
            cursor.execute(query)
            users = cursor.fetchall()
            
            cursor.close()
            conn.close()
            
            # Remove passwords from response
            for user in users:
                user.pop('password', None)
            
            return jsonify({
                'status': 'vulnerable',
                'message': 'Vulnerable query executed (SQL Injection possible)',
                'detected_injections': all_injections,
                'query_executed': query,
                'results': users,
                'analysis': {
                    'username_analysis': injection_result,
                    'password_analysis': password_injection
                }
            }), 200
            
        except Exception as e:
            return jsonify({
                'status': 'error',
                'message': f'SQL Error: {str(e)}',
                'detected_injections': all_injections if 'all_injections' in locals() else []
            }), 500
    
    # If no injection detected, use safe query
    try:
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        
        query = "SELECT * FROM users WHERE username = %s AND password = %s"
        cursor.execute(query, (username, password))
        user = cursor.fetchone()
        
        cursor.close()
        conn.close()
        
        if user:
            user.pop('password', None)
            return jsonify({
                'status': 'success',
                'message': 'Login successful',
                'user': user,
                'query_executed': f"SELECT * FROM users WHERE username = '{username}' AND password = '{password}'"
            }), 200
        else:
            return jsonify({
                'status': 'error',
                'message': 'Invalid credentials'
            }), 401
            
    except Exception as e:
        return jsonify({
            'status': 'error',
            'message': f'Database error: {str(e)}'
        }), 500

@app.route('/api/register', methods=['POST'])
def register():
    data = request.json
    username = data.get('username', '')
    password = data.get('password', '')
    age = data.get('age', 0)
    date_of_join = data.get('date_of_join', '')
    
    # Check for SQL injection
    for field_name, field_value in [('username', username), ('password', password)]:
        injection_result = detector.detect_injection(field_value)
        if injection_result['has_injection']:
            return jsonify({
                'status': 'blocked',
                'message': f'SQL Injection detected in {field_name}!',
                'injections': injection_result['injections_found']
            }), 403
    
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        
        # Safe parameterized insert
        cursor.execute(
            "INSERT INTO users (username, password, age, date_of_join) VALUES (%s, %s, %s, %s)",
            (username, password, age, date_of_join)
        )
        conn.commit()
        
        user_id = cursor.lastrowid
        cursor.close()
        conn.close()
        
        return jsonify({
            'status': 'success',
            'message': 'User registered successfully',
            'user_id': user_id
        }), 201
        
    except Exception as e:
        return jsonify({
            'status': 'error',
            'message': f'Registration failed: {str(e)}'
        }), 500

@app.route('/api/users', methods=['GET'])
def get_users():
    """Get all users (for demonstration)"""
    try:
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        
        cursor.execute("SELECT id, username, age, date_of_join FROM users")
        users = cursor.fetchall()
        
        cursor.close()
        conn.close()
        
        return jsonify({
            'status': 'success',
            'users': users
        }), 200
        
    except Exception as e:
        return jsonify({
            'status': 'error',
            'message': str(e)
        }), 500

@app.route('/api/analyze', methods=['POST'])
def analyze_input():
    """Analyze input for SQL injection patterns"""
    data = request.json
    input_text = data.get('input', '')
    
    analysis = detector.detect_injection(input_text)
    
    return jsonify({
        'status': 'success',
        'analysis': analysis
    }), 200

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=True)
