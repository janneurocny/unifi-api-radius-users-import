# UniFi API Radius Users Import

Import users into the integrated Radius server in UniFi using the API. It supports CSV export from daloRADIUS and the text format configuration file from a Radius server.

> [!CAUTION] 
> This code was generated using ChatGPT. Use at your own risk.

---

## Tested on
- UniFi Dream Machine PRO (UniFi OS 4.0.21, Network 8.6.9)

---

## How to use
- **UniFi device** must be on the same local network as the machine running the script!
- It is not possible to call the API via the `unifi.ui.com` portal.

---

### Creating a UniFi Admin

> [!NOTE] 
> If you use a local admin account (without two-factor authentication) to log into your UniFi device, skip this steps.

1. Go to UniFi management and navigate to `Admins & Users`.
2. Create a local user with the role `Super Admin`.

---

### Preparing Files with Users

#### daloRADIUS
> [!NOTE] 
> Tested on daloRADIUS 2.0 beta / 9 Feb 2023.

1. Log in as admin.
2. Click on `Management` in the menu.
3. In the left panel, click `List Users`.
4. Above the user table, click `CSV Export` at the top right.

---

#### Radius Server Configuration File
> [!NOTE] 
> Tested on FreeRADIUS 3.0 on Debian 12, assuming users are stored in the file.

1. Open the file `/etc/freeradius/3.0/users`.
2. Copy only the section with usernames and passwords. For example:


   ```plaintext
   user1 Cleartext-Password := "password1"
   user2 Cleartext-Password := "password2"
   user3 Cleartext-Password := "password3"
   ```

3. Save it as a TXT file on your computer, e.g., `users.txt`.

---

### Importing

Choose your import method:

- **[Python](https://github.com/janneurocny/unifi-api-radius-users-import/Python)**
- **[PHP](https://github.com/janneurocny/unifi-api-radius-users-import/PHP)**