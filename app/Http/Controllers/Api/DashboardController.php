<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats()
    {
        $stats = [
            'total_users' => User::count(),
            'active_users' => User::where('status', 'active')->count(),
            'total_sales' => 45678, // Replace with actual sales model
            'total_revenue' => 123456, // Replace with actual revenue calculation
            'page_views' => 987654, // Replace with actual analytics
            'growth_percentage' => 12.5, // Replace with actual calculation
            'monthly_data' => $this->getMonthlyData(),
        ];

        return response()->json($stats);
    }

    public function chartData($type)
    {
        $data = [];

        switch ($type) {
            case 'monthly':
                $data = $this->getMonthlyChartData();
                break;
            case 'bar':
                $data = $this->getBarChartData();
                break;
            case 'line':
                $data = $this->getLineChartData();
                break;
            case 'pie':
                $data = $this->getPieChartData();
                break;
            case 'area':
                $data = $this->getAreaChartData();
                break;
            case 'radar':
                $data = $this->getRadarChartData();
                break;
            default:
                $data = $this->getMonthlyChartData();
        }

        return response()->json([
            'data' => $data,
            'type' => $type,
        ]);
    }

    public function recentActivity()
    {
        // Replace with actual activity log
        $activities = [
            [
                'id' => 1,
                'type' => 'user_registered',
                'message' => 'New user registration',
                'time' => '2 min ago',
                'user' => 'John Doe',
            ],
            [
                'id' => 2,
                'type' => 'order_completed',
                'message' => 'Order #1234 completed',
                'time' => '5 min ago',
                'user' => 'Jane Smith',
            ],
        ];

        return response()->json($activities);
    }

    private function getMonthlyData()
    {
        // Replace with actual monthly data calculation
        return [
            ['month' => 'January', 'users' => 1200, 'sales' => 4000, 'revenue' => 25000],
            ['month' => 'February', 'users' => 1900, 'sales' => 3000, 'revenue' => 22000],
            ['month' => 'March', 'users' => 800, 'sales' => 2000, 'revenue' => 18000],
            ['month' => 'April', 'users' => 2780, 'sales' => 2780, 'revenue' => 32000],
            ['month' => 'May', 'users' => 1890, 'sales' => 1890, 'revenue' => 28000],
            ['month' => 'June', 'users' => 2390, 'sales' => 2390, 'revenue' => 35000],
        ];
    }

    private function getMonthlyChartData()
    {
        return [
            ['name' => 'Jan', 'Investment' => 100, 'Loss' => 80, 'Profit' => 120, 'Maintenance' => 60],
            ['name' => 'Feb', 'Investment' => 150, 'Loss' => 60, 'Profit' => 180, 'Maintenance' => 80],
            ['name' => 'Mar', 'Investment' => 80, 'Loss' => 40, 'Profit' => 90, 'Maintenance' => 50],
            ['name' => 'Apr', 'Investment' => 120, 'Loss' => 50, 'Profit' => 140, 'Maintenance' => 70],
            ['name' => 'May', 'Investment' => 200, 'Loss' => 90, 'Profit' => 250, 'Maintenance' => 100],
            ['name' => 'Jun', 'Investment' => 180, 'Loss' => 70, 'Profit' => 220, 'Maintenance' => 90],
        ];
    }

    private function getBarChartData()
    {
        return [
            ['name' => 'Jan', 'users' => 400, 'sales' => 240, 'revenue' => 2400],
            ['name' => 'Feb', 'users' => 300, 'sales' => 139, 'revenue' => 2210],
            ['name' => 'Mar', 'users' => 200, 'sales' => 980, 'revenue' => 2290],
            ['name' => 'Apr', 'users' => 278, 'sales' => 390, 'revenue' => 2000],
            ['name' => 'May', 'users' => 189, 'sales' => 480, 'revenue' => 2181],
            ['name' => 'Jun', 'users' => 239, 'sales' => 380, 'revenue' => 2500],
        ];
    }

    private function getLineChartData()
    {
        return $this->getBarChartData();
    }

    private function getPieChartData()
    {
        return [
            ['name' => 'Desktop', 'value' => 400, 'color' => '#673ab7'],
            ['name' => 'Mobile', 'value' => 300, 'color' => '#2196f3'],
            ['name' => 'Tablet', 'value' => 300, 'color' => '#4caf50'],
            ['name' => 'Other', 'value' => 200, 'color' => '#ff9800'],
        ];
    }

    private function getAreaChartData()
    {
        return $this->getBarChartData();
    }

    private function getRadarChartData()
    {
        return [
            ['subject' => 'Performance', 'A' => 120, 'B' => 110, 'fullMark' => 150],
            ['subject' => 'Security', 'A' => 98, 'B' => 130, 'fullMark' => 150],
            ['subject' => 'Usability', 'A' => 86, 'B' => 130, 'fullMark' => 150],
            ['subject' => 'Features', 'A' => 99, 'B' => 100, 'fullMark' => 150],
            ['subject' => 'Support', 'A' => 85, 'B' => 90, 'fullMark' => 150],
            ['subject' => 'Quality', 'A' => 65, 'B' => 85, 'fullMark' => 150],
        ];
    }
}
