<?php
class Calorie {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // ---------------- Food Entry ----------------
    public function addFoodEntry($user_id, $data) {
        $food_name = $this->conn->real_escape_string($data['food_name']);
        $quantity = $this->conn->real_escape_string($data['quantity']);
        $calories = (int)$data['calories'];
        $meal_type = $this->conn->real_escape_string($data['meal_type']);
        $serving_size = isset($data['serving_size']) ? $this->conn->real_escape_string($data['serving_size']) : null;
        $notes = isset($data['notes']) ? $this->conn->real_escape_string($data['notes']) : null;
        $entry_date = date('Y-m-d');
        
        $sql = "INSERT INTO food_entries (user_id, food_name, quantity, calories, meal_type, serving_size, notes, entry_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ississss", $user_id, $food_name, $quantity, $calories, $meal_type, $serving_size, $notes, $entry_date);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Food entry added successfully!'];
        } else {
            return ['success' => false, 'message' => 'Failed to add food entry: ' . $stmt->error];
        }
    }

    // ---------------- Activity Entry ----------------
    public function addActivityEntry($user_id, $data) {
        $activity_name = $this->conn->real_escape_string($data['activity_name']);
        $duration = (int)$data['duration'];
        $calories_burned = (int)$data['calories_burned'];
        $activity_date = isset($data['activity_date']) ? $this->conn->real_escape_string($data['activity_date']) : date('Y-m-d');
        $activity_type = isset($data['activity_type']) ? $this->conn->real_escape_string($data['activity_type']) : null;
        $notes = isset($data['notes']) ? $this->conn->real_escape_string($data['notes']) : null;
        
        $sql = "INSERT INTO activity_calories (user_id, activity_name, duration, calories_burned, activity_date, activity_type, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("isiisss", $user_id, $activity_name, $duration, $calories_burned, $activity_date, $activity_type, $notes);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Activity added successfully!'];
        } else {
            return ['success' => false, 'message' => 'Failed to add activity: ' . $stmt->error];
        }
    }

    // ---------------- Delete Entry ----------------
    public function deleteEntry($id, $type) {
        if ($type === 'food') {
            $sql = "DELETE FROM food_entries WHERE id = ?";
        } else {
            $sql = "DELETE FROM activity_calories WHERE id = ?";
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Entry deleted successfully!'];
        } else {
            return ['success' => false, 'message' => 'Failed to delete entry: ' . $stmt->error];
        }
    }

    // ---------------- Today's Entries ----------------
    public function getTodayFoodEntries($user_id) {
        $today = date('Y-m-d');
        $sql = "SELECT * FROM food_entries WHERE user_id = ? AND entry_date = ? ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("is", $user_id, $today);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getTodayActivityEntries($user_id) {
        $today = date('Y-m-d');
        $sql = "SELECT * FROM activity_calories WHERE user_id = ? AND activity_date = ? ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("is", $user_id, $today);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // ---------------- Today's Calories ----------------
    public function getTodayCalories($user_id) {
        $today = date('Y-m-d');
        $sql = "SELECT SUM(calories) as total FROM food_entries WHERE user_id = ? AND entry_date = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("is", $user_id, $today);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['total'] ?? 0;
    }

    public function getTodayBurnedCalories($user_id) {
        $today = date('Y-m-d');
        $sql = "SELECT SUM(calories_burned) as total FROM activity_calories WHERE user_id = ? AND activity_date = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("is", $user_id, $today);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['total'] ?? 0;
    }

    // ---------------- Weekly Data ----------------
    public function getWeeklyCalorieData($user_id) {
        $start_date = date('Y-m-d', strtotime('-6 days')); // last 7 days
        $end_date = date('Y-m-d');

        // Food intake
        $sql_intake = "SELECT entry_date, SUM(calories) as total 
                       FROM food_entries 
                       WHERE user_id = ? AND entry_date BETWEEN ? AND ? 
                       GROUP BY entry_date 
                       ORDER BY entry_date";
        $stmt = $this->conn->prepare($sql_intake);
        $stmt->bind_param("iss", $user_id, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        $intake_data = [];
        $total_intake = 0;
        $days_count = 0;
        while ($row = $result->fetch_assoc()) {
            $intake_data[$row['entry_date']] = $row['total'];
            $total_intake += $row['total'];
            $days_count++;
        }

        // Burned calories
        $sql_burned = "SELECT activity_date, SUM(calories_burned) as total 
                       FROM activity_calories 
                       WHERE user_id = ? AND activity_date BETWEEN ? AND ? 
                       GROUP BY activity_date 
                       ORDER BY activity_date";
        $stmt = $this->conn->prepare($sql_burned);
        $stmt->bind_param("iss", $user_id, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        $burned_data = [];
        $total_burned = 0;
        while ($row = $result->fetch_assoc()) {
            $burned_data[$row['activity_date']] = $row['total'];
            $total_burned += $row['total'];
        }

        $avg_intake = $days_count > 0 ? round($total_intake / $days_count) : 0;
        $avg_burned = $days_count > 0 ? round($total_burned / $days_count) : 0;

        return [
            'intake_data' => $intake_data,
            'burned_data' => $burned_data,
            'total_intake' => $total_intake,
            'total_burned' => $total_burned,
            'avg_intake' => $avg_intake,
            'avg_burned' => $avg_burned
        ];
    }

    // ---------------- Daily Goal ----------------
    public function calculateDailyCalorieGoal($user_id, $weight_goal) {
        $base_calories = 2000;
        switch ($weight_goal) {
            case 'Loss': return $base_calories - 500;
            case 'Gain': return $base_calories + 500;
            default: return $base_calories;
        }
    }

    // ---------------- Meal Distribution ----------------
    public function getMealDistribution($user_id, $start_date, $end_date) {
        $sql = "SELECT meal_type, SUM(calories) as total_calories 
                FROM food_entries 
                WHERE user_id = ? AND entry_date BETWEEN ? AND ? 
                GROUP BY meal_type 
                ORDER BY total_calories DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("iss", $user_id, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();

        $meals = [];
        $total = 0;
        while ($row = $result->fetch_assoc()) {
            $meals[] = $row;
            $total += $row['total_calories'];
        }

        foreach ($meals as &$meal) {
            $meal['percentage'] = $total > 0 ? round(($meal['total_calories'] / $total) * 100) : 0;
        }

        return $meals;
    }


    public function getMonthlyCalories($user_id) {
    // মাসের প্রথম এবং শেষ দিন বের করা
    $start_date = date('Y-m-01'); // মাসের প্রথম দিন
    $end_date = date('Y-m-t');    // মাসের শেষ দিন

    // Food entries থেকে মোট ক্যালোরি যোগ করা
    $sql = "SELECT SUM(calories) as total_calories 
            FROM food_entries 
            WHERE user_id = ? 
            AND entry_date BETWEEN ? AND ?";

    $stmt = $this->conn->prepare($sql);
    $stmt->bind_param("iss", $user_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    return $row['total_calories'] ?? 0;
}


  // Today's total calories for a user
    public function getTodayTotalCalories($user_id) {
        $today = date('Y-m-d');
        $sql = "SELECT SUM(calories) as total_calories 
                FROM food_entries  
                WHERE user_id = ? AND DATE(created_at) = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("is", $user_id, $today);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['total_calories'] ?? 0;
    }

   
    public function addWaterEntry($user_id, $amount) {
    $stmt = $this->conn->prepare("INSERT INTO water_intake (user_id, amount, created_at) VALUES (?, ?, NOW())");
    $stmt->bind_param("id", $user_id, $amount);
    if ($stmt->execute()) {
        return ['success' => true, 'message' => "$amount L water logged successfully."];
    } else {
        return ['success' => false, 'message' => 'Failed to log water.'];
    }
}


public function getTodayWaterIntake($user_id) {
    $today = date('Y-m-d');
    $stmt = $this->conn->prepare("SELECT SUM(amount) as total FROM water_intake WHERE user_id = ? AND DATE(created_at) = ?");
    $stmt->bind_param("is", $user_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['total'] ?? 0;  // যদি null হয় তাহলে 0
}


}
?>
