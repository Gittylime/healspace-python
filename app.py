from flask import Flask, render_template, request, redirect, url_for, flash
from flask_sqlalchemy import SQLAlchemy
from flask_login import LoginManager, login_user, logout_user, login_required, current_user
from werkzeug.security import generate_password_hash, check_password_hash
from models import db, User, Therapist, Session
from config import Config
from datetime import datetime
import bcrypt

app = Flask(__name__)
app.config.from_object(Config)
db.init_app(app)
login_manager = LoginManager()
login_manager.init_app(app)
login_manager.login_view = 'user_login'

@login_manager.user_loader
def load_user(user_id):
    user = User.query.get(int(user_id))
    if user:
        return user
    therapist = Therapist.query.get(int(user_id))
    return therapist

@app.route('/')
def index():
    return render_template('index.html')

@app.route('/user_register', methods=['GET', 'POST'])
def user_register():
    if request.method == 'POST':
        name = request.form['name']
        email = request.form['email']
        password = request.form['password']
        bio = request.form['bio']
        hashed_password = bcrypt.hashpw(password.encode('utf-8'), bcrypt.gensalt()).decode('utf-8')
        new_user = User(name=name, email=email, password=hashed_password, bio=bio)
        db.session.add(new_user)
        db.session.commit()
        flash('Registration successful! Please log in.')
        return redirect(url_for('user_login'))
    return render_template('user_register.html')

@app.route('/therapist_register', methods=['GET', 'POST'])
def therapist_register():
    if request.method == 'POST':
        name = request.form['name']
        email = request.form['email']
        password = request.form['password']
        specialty = request.form['specialty']
        bio = request.form['bio']
        hashed_password = bcrypt.hashpw(password.encode('utf-8'), bcrypt.gensalt()).decode('utf-8')
        new_therapist = Therapist(name=name, email=email, password=hashed_password, specialty=specialty, bio=bio)
        db.session.add(new_therapist)
        db.session.commit()
        flash('Registration successful! Please log in.')
        return redirect(url_for('therapist_login'))
    return render_template('therapist_register.html')

@app.route('/user_login', methods=['GET', 'POST'])
def user_login():
    if request.method == 'POST':
        email = request.form['email']
        password = request.form['password']
        user = User.query.filter_by(email=email).first()
        if user and bcrypt.checkpw(password.encode('utf-8'), user.password.encode('utf-8')):
            login_user(user)
            return redirect(url_for('user_dashboard'))
        flash('Invalid credentials.')
    return render_template('user_login.html')

@app.route('/therapist_login', methods=['GET', 'POST'])
def therapist_login():
    if request.method == 'POST':
        email = request.form['email']
        password = request.form['password']
        therapist = Therapist.query.filter_by(email=email).first()
        if therapist and bcrypt.checkpw(password.encode('utf-8'), therapist.password.encode('utf-8')):
            login_user(therapist)
            return redirect(url_for('therapist_dashboard'))
        flash('Invalid credentials.')
    return render_template('therapist_login.html')

@app.route('/user_dashboard')
@login_required
def user_dashboard():
    therapists = Therapist.query.all()
    sessions = Session.query.filter_by(user_id=current_user.id).all()
    return render_template('user_dashboard.html', therapists=therapists, sessions=sessions)

@app.route('/therapist_dashboard')
@login_required
def therapist_dashboard():
    sessions = Session.query.filter_by(therapist_id=current_user.id).all()
    return render_template('therapist_dashboard.html', sessions=sessions)

@app.route('/book_session/<int:therapist_id>', methods=['GET', 'POST'])
@login_required
def book_session(therapist_id):
    therapist = Therapist.query.get_or_404(therapist_id)
    if request.method == 'POST':
        date = datetime.strptime(request.form['date'], '%Y-%m-%dT%H:%M')
        new_session = Session(user_id=current_user.id, therapist_id=therapist_id, date=date)
        db.session.add(new_session)
        db.session.commit()
        flash('Session booked successfully!')
        return redirect(url_for('user_dashboard'))
    return render_template('book_session.html', therapist=therapist)

@app.route('/session_action/<int:session_id>/<action>')
@login_required
def session_action(session_id, action):
    session = Session.query.get_or_404(session_id)
    if session.therapist_id == current_user.id:
        session.status = action
        db.session.commit()
        flash(f'Session {action} successfully!')
    return redirect(url_for('therapist_dashboard'))

@app.route('/therapist_profile/<int:therapist_id>')
def therapist_profile(therapist_id):
    therapist = Therapist.query.get_or_404(therapist_id)
    return render_template('therapist_profile.html', therapist=therapist)

@app.route('/user_profile')
@login_required
def user_profile():
    return render_template('user_profile.html')

@app.route('/logout')
@login_required
def logout():
    logout_user()
    return redirect(url_for('index'))

if __name__ == '__main__':
    with app.app_context():
        db.create_all()
    app.run(debug=True)