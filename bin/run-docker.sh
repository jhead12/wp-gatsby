#!/usr/bin/env bash

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo "Docker could not be found. Please install Docker and try again."
    exit 1
fi

# Check if Docker daemon is running
if ! docker info > /dev/null 2>&1; then
    echo "Docker daemon is not running. Please start the Docker service and try again."
    exit 1
fi

set -euo pipefail

##
# Use this script through Composer scripts in the package.json.
# To quickly build and run the docker-compose scripts for an app or automated testing
# run the command below after running `composer install --no-dev` with the respective
# flag for what you need.
##
print_usage_instructions() {
    echo "Usage: composer build-and-run -- [-a|-t]"
    echo "       -a  Spin up a WordPress installation."
    echo "       -t  Run the automated tests."
    exit 1
}

if [ $# -eq 0 ]; then
    print_usage_instructions
fi

env_file=".env.dist"
echo env_file

if [ ! -f "$env_file" ]; then
    echo "No file found at $env_file. Please check the path or specify a different environment file with the -e option."
    exit 1
fi

# Load the environment variables from the .env file
export $(cat $env_file | grep -v '#' | xargs)
subcommand=$1; shift
case "$subcommand" in
    "build")
        while getopts ":at" opt; do
            case $opt in
                a)
                    echo "Building WordPress image with WP_VERSION=${WP_VERSION:-5.4} and PHP_VERSION=${PHP_VERSION:-7.4}"
                    docker build -f docker/app.Dockerfile \
                        -t wpgatsby-app:latest \
                        --build-arg WP_VERSION=${WP_VERSION:-5.4} \
                        --build-arg PHP_VERSION=${PHP_VERSION:-7.4} .
                    ;;
                t)
                    echo "Building WordPress image with WP_VERSION=${WP_VERSION:-5.4} and PHP_VERSION=${PHP_VERSION:-7.4}"
                    docker build -f docker/app.Dockerfile \
                        -t wpgatsby-app:latest \
                        --build-arg WP_VERSION=${WP_VERSION:-5.4} \
                        --build-arg PHP_VERSION=${PHP_VERSION:-7.4} .

                    echo "Building Testing image with USE_XDEBUG=${USE_XDEBUG:-}"
                    docker build -f docker/testing.Dockerfile \
                        -t wpgatsby-testing:latest \
                        --build-arg USE_XDEBUG=${USE_XDEBUG:-} .
                    ;;
                \?)
                    print_usage_instructions
                    ;;
                *)
                    print_usage_instructions
                    ;;
            esac
        done
        shift $((OPTIND - 1))
        ;;
    "run")
        while getopts "e:at" opt; do
            case $opt in
                e)
                    env_file=${OPTARG}
                    if [ ! -f "$env_file" ]; then
                        echo "No file found at $env_file. Please check the path."
                        exit 1
                    fi
                    ;;
                a)
                    echo "Running WordPress installation with environment file: $env_file"
                    docker-compose up --scale testing=0
                    ;;
                t)
                    source "$env_file"
                    echo "Running automated tests with environment file: $env_file"
                    docker-compose run --rm \
                        -e SUITES=${SUITES:-wpunit} \
                        -e COVERAGE=${COVERAGE:-} \
                        -e DEBUG=${DEBUG:-} \
                        -e SKIP_TESTS_CLEANUP=${SKIP_TESTS_CLEANUP:-} \
                        -e LOWEST=${LOWEST:-} \
                        testing --scale app=0
                    ;;
                \?)
                    print_usage_instructions
                    ;;
                *)
                    print_usage_instructions
                    ;;
            esac
        done
        shift $((OPTIND - 1))
        ;;
    \?)
        print_usage_instructions
        ;;
    *)
        print_usage_instructions
        ;;
esac