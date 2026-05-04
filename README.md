# TRACE Project Scaffold

This workspace now contains the initial scaffold for the TRACE capstone system.

## Folders
- `database` - MySQL schema
- `backend` - PHP REST API scaffold
- `admin` - Bootstrap admin pages
- `mobile` - React Native app scaffold

## Suggested Build Order
1. Import `database/schema.sql` into MySQL
2. Connect PHP controllers to the database
3. Implement registration and login
4. Build the parent student-management flow
5. Build booking and driver assignment
6. Add GPS tracking and map display
7. Add messaging and notifications


$env:Path = [System.Environment]::GetEnvironmentVariable('Path','Machine') + ';' + [System.Environment]::GetEnvironmentVariable('Path','User')

