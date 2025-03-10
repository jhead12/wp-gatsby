#!/usr/bin/env bash

set -euo pipefail

# Function to check if Docker is installed
check_docker() {
    if ! command -v docker &> /dev/null; then
        echo "Docker could not be found. Please install Docker and try again."
        exit 1
    fi
}

# Function to check if Docker daemon is running
check_docker_daemon() {
    if ! docker info > /dev/null 2>&1; then
        echo "Docker daemon is not running. Please start the Docker service and try again."
        exit 1
    fi
}

# Function to print usage instructions
print_usage_instructions() {
    echo "Usage: composer build-and-run -- [-a|-t]"
    echo "       -a  Spin up a WordPress installation."
    echo "       -t  Run the automated tests."
    exit 1
}

# Function to load environment variables
load_env_variables() {
    local env_file="$1"
    if [ ! -f "$env_file" ]; then
        echo "No file found at $env_file. Please check the path or specify a different environment file with the -e option."
        exit 1
    fi
    export $(grep -v '^#' "$env_file" | xargs)
}

# Function to build Docker images
build_images() {
    local build_type="$1"
    case "$build_type" in
        a)
            echo "Building WordPress image with WP_VERSION=${WP_VERSION:-6.7.2} and PHP_VERSION=${PHP_VERSION:-8.3}"
            docker build -f docker/app.Dockerfile -t wpgatsby-app:latest \
                --build-arg WP_VERSION=${WP_VERSION:-6.7.2} \
                --build-arg PHP_VERSION=${PHP_VERSION:-8.3} .
            ;;
        t)
            echo "Building WordPress image with WP_VERSION=${WP_VERSION:-6.7.2} and PHP_VERSION=${PHP_VERSION:-8.3}"
            docker build -f docker/app.Dockerfile -t wpgatsby-app:latest \
                --build-arg WP_VERSION=${WP_VERSION:-6.7.2} \
                --build-arg PHP_VERSION=${PHP_VERSION:-8.3} .
            echo "Building Testing image with USE_XDEBUG=${USE_XDEBUG:-}"
            docker build -f docker/testing.Dockerfile -t wpgatsby-testing:latest \
                --build-arg USE_XDEBUG=${USE_XDEBUG:-} .
            ;;
        *)
            print_usage_instructions
            ;;
    esac
}

# Function to run Docker containers
run_containers() {
    local env_file="$1"
    local run_type="$2"
    case "$run_type" in
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
        *)
            print_usage_instructions
            ;;
    esac
}

main() {
    check_docker
    check_docker_daemon

    if [ $# -eq 0 ]; then
        print_usage_instructions
    fi

    env_file=".env.dist"
    subcommand=$1; shift
    case "$subcommand" in
        "build")
            while getopts ":at" opt; do
                build_images "$opt"
            done
            shift $((OPTIND - 1))
            ;;
        "run")
            while getopts "e:at" opt; do
                case $opt in
                    e)
                        env_file=${OPTARG}
                        ;;
                    *)
                        run_containers "$env_file" "$opt"
                        ;;
                esac
            done
            shift $((OPTIND - 1))
            ;;
        *)
            print_usage_instructions
            ;;
    esac
}

main "$@"
