## Install Git & pip
`apt-get -y install git python-pip`

## Install Docker
`wget -qO- https://get.docker.com/ | sh`

## Clone the project
`git clone https://github.com/mike-code/slidely`

## Set permissions
`chown 1000:1000 -R src`

## Init docker swarm (or join existing)
`docker swarm init`

## Deploy the stack
`docker stack deploy -c docker-compose.yml slidely`
