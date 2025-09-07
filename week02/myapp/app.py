from flask import Flask

app = Flask(__name__)

@app.route('/')
def hello():
    return """
    <html>
      <head>
        <title>Hello Azure</title>
        <style>
          body { background: linear-gradient(to right, #0078d7, #00aaff); color: white; font-family: Arial; text-align: center; padding: 50px; }
          h1 { font-size: 3em; }
        </style>
      </head>
      <body>
        <h1>Hello, Azure! 🌍</h1>
        <p>Served from a Flask container 🐳</p>
      </body>
    </html>
    """
if __name__ == '__main__':
    app.run(host='0.0.0.0', port=80)