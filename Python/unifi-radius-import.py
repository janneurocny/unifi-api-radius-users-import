import os
import json
import requests
from requests.packages.urllib3.exceptions import InsecureRequestWarning

# Suppress InsecureRequestWarning
requests.packages.urllib3.disable_warnings(InsecureRequestWarning)

def get_input(prompt):
    try:
        return input(prompt)
    except KeyboardInterrupt:
        print("\nOperation cancelled by user.")
        exit()

def authenticate(api_url, username, password):
    auth_url = f"{api_url}/api/auth/login"
    payload = {"username": username, "password": password}
    headers = {"Content-Type": "application/json"}

    try:
        response = requests.post(auth_url, json=payload, headers=headers, verify=False)
        response.raise_for_status()
        csrf_token = response.headers.get("X-CSRF-Token")
        if not csrf_token:
            print("[ERROR] X-CSRF-Token not found in response headers.")
            exit()
        return csrf_token, response.cookies
    except requests.exceptions.RequestException as e:
        print(f"[ERROR] Authentication failed: {e}")
        exit()

def parse_file(file_path):
    try:
        with open(file_path, "r") as file:
            lines = file.readlines()

        parsed_data = []
        for line in lines:
            line = line.strip()
            if "," in line and "Cleartext-Password" in line:
                parts = line.split(",")
                username = parts[2].strip()
                password = parts[4].strip()
                parsed_data.append({
                    "name": username,
                    "x_password": password,
                    "tunnel_medium_type": "",
                    "tunnel_type": "",
                    "vlan": ""
                })
            elif "Cleartext-Password :=" in line:
                username, password = line.split("Cleartext-Password :=")
                username = username.strip()
                password = password.strip().strip('"')
                parsed_data.append({
                    "name": username,
                    "x_password": password,
                    "tunnel_medium_type": "",
                    "tunnel_type": "",
                    "vlan": ""
                })
        return parsed_data
    except Exception as e:
        print(f"[ERROR] Failed to read or parse the file: {e}")
        exit()

def send_data(api_url, csrf_token, cookies, data):
    batch_url = f"{api_url}/proxy/network/v2/api/site/default/radius/users/batch_add"
    headers = {
        "Content-Type": "application/json",
        "X-CSRF-Token": csrf_token
    }
    try:
        response = requests.post(batch_url, json=data, headers=headers, cookies=cookies, verify=False)
        response.raise_for_status()
        print("[SUCCESS] Server Response:")
        print(response.json())
    except requests.exceptions.RequestException as e:
        print(f"[ERROR] Failed to send data: {e}")
        exit()

def main():
    print("\033[1;34mUniFi API Radius Users Import\033[0m")
    print("\033[1;33mGitHub repository: https://github.com/janneurocny/unifi-api-radius-users-import\033[0m")
    api_url = get_input("Enter API URL: ").strip()
    username = get_input("Enter username: ").strip()
    password = get_input("Enter password: ").strip()
    file_path = get_input("Enter path to the file: ").strip()

    if not os.path.exists(file_path):
        print("[ERROR] File does not exist.")
        exit()

    csrf_token, cookies = authenticate(api_url, username, password)
    parsed_data = parse_file(file_path)

    if not parsed_data:
        print("[ERROR] No valid data found in the file.")
        exit()

    send_data(api_url, csrf_token, cookies, parsed_data)

if __name__ == "__main__":
    main()
