# Python 3.9
FROM python:3.9-slim

# Working folder in container
WORKDIR /app

# copy app files into container
COPY . /app

# install dependencies
RUN pip install flask

# open the port 80
EXPOSE 80

# define command to be executed in container
CMD ["python", "app.py"]
