<?php
namespace Vanderbilt\REDCap\UnitTests\Utility;


class UsersGenerator {

    public function generate(int $totalUsers, ?int $seed = null): array {
        // If a seed is provided, use it for reproducible results.
        if ($seed !== null) {
            mt_srand($seed);
        }
    
        // Extended static arrays of names and adjectives.
        $firstNames = ['Alice', 'Bob', 'Charlie', 'Diana', 'Eve', 'Frank', 'Grace', 'Hank', 'Ivy', 'John'];
        $lastNames  = ['Smith', 'Johnson', 'Williams', 'Jones', 'Brown', 'Davis', 'Miller', 'Wilson', 'Taylor', 'Anderson'];
        $adjectives = ['swift', 'silent', 'fierce', 'bold', 'mighty', 'brave', 'sharp'];
    
        $users = [];
        for ($i = 0; $i < $totalUsers; $i++) {
            // Generate a more random username using an adjective, a random number, and the current index.
            $randomAdjective = $adjectives[mt_rand(0, count($adjectives) - 1)];
            $randomNumForUsername = mt_rand(100, 999);
            $username = "{$randomAdjective}-user-{$randomNumForUsername}-$i";
    
            // Randomly select first and last names.
            $firstName = $firstNames[mt_rand(0, count($firstNames) - 1)];
            $lastName  = $lastNames[mt_rand(0, count($lastNames) - 1)];
    
            // Append a random two-digit number to the email address.
            $randomNumForEmail = mt_rand(10, 99);
            $email = strtolower($firstName) . '.' . strtolower($lastName) . $randomNumForEmail . "@example.com";
    
            $options = [
                'username'   => $username,
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'email'      => $email,
            ];
            $users[$username] = $options;
        }
    
        return $users;
    }
    

    /* public function generateUsingFaker(int $totalUsers, ?int $seed = null): array {
        if (!class_exists(\Faker\Factory::class)) {
            return [];
        }
        $faker = \Faker\Factory::create();
        if($seed) $faker->seed($seed);

        $users = [];
        for ($i = 0; $i < $totalUsers; $i++) { 
            $username = "test-user-$i";
            $options = [
                'username'   => $username,
                'first_name' => $faker->firstName(),
                'last_name'  => $faker->lastName(),
                'email'      => $faker->email(),
            ];
            $users[$username] = $options;
        }

        return $users;
    } */
}
