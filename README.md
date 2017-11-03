## Install Git & pip
`apt-get -y install git python-pip`

## Install Docker
`wget -qO- https://get.docker.com/ | sh`

## Install Docker Compose
`pip install docker-compose`

## Clone the project
`git clone https://github.com/mike-code/slidely`

## Set permissions
`chown 1000:1000 -R src`

## Start it
`docker-compose up [-d]`