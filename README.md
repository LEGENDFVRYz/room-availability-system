# 🚪 **(CPE) Room Availability System**

**Web-Based Schedule-Driven Room Availability and Scheduling System for the PUP Computer Engineering Department**

This system will be used in the Computer Engineering (CPE) Department to manage shared academic spaces and address the increased demand for efficient room utilization, effectively preventing scheduling conflicts and optimizing the use of classrooms. Additionally, it will serve to provide students and faculty with class schedules and urgent notices, such as room changes or class cancellations.


- **Note:** this project was made purely for **Academic Purposes Only**


## 🔧 Prerequisites

Ensure you have the following tools installed on your system:

1. **Git**: [Download Git](https://git-scm.com/)
2. **Composer**: [Download Composer](https://getcomposer.org/)
3. **Node.js and npm**: [Download Node.js](https://nodejs.org/) (npm is included with Node.js)
4. **PHP development environment**: Use XAMPP, HERD, or set up PHP & a web server manually.

- **Note:** It is recommended to use XAMPP if you are new, as it provides a simple and easy setup.

## ⚙️ Installation & Setup

Follow these steps to get the application up and running locally:

1. Clone the project repository

   ```sh
   git clone https://github.com/LEGENDFVRYz/room-availability-system.git
   cd room-availability-system
   ```

2. Clone the project repository

   ```sh
   git checkout -b <your-initials-plus-lastname>     #Ex. "sljcruz" "cakvillanueva"
   git pull origin main
   git pull origin staging
   ```

3. Install Backend and Frontend Dependencies

   ```sh
   composer install
   npm install
   ```

4. Configure Environment Variables for applications

   - Create .env files

   ```sh
   /* copy for Windows; cp for Unix-based systems */
   copy .env.example .env
   ```

   - Update the necessary variables based on your setup

5. Generate Application Key

   ```sh
   php artisan key:generate
   ```

6. Run Database Migrations and Data Seeder

   ```sh
   php artisan migrate --seed
   ```

7. Start the Development Servers

   ```sh
   composer run dev
   ```

8. Access the applications
   - http://127.0.0.1:8000

   **Note:** You may change the application URL in the .env file if the default address is not available.

