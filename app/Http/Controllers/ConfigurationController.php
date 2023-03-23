<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConfigurationRequest;
use App\Http\Requests\UpdateConfigurationRequest;
use App\Models\Configuration;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ConfigurationController extends Controller
{
    /**
     * Запрос на изменение
     * 
     * @param Request $request
     * 
     * @return JsonResponse
     */
    public function change(Request $request): JsonResponse
    {
        $this->setChange('KOLSYL', (int) $request->KOLSYL);
        $this->setChange('PAYLEVEL', (int) $request->PAYLEVEL);
        $this->setChange('PAYSUM', (int) $request->PAYSUM);
        $this->setChange('PAYRESERVE', (int) $request->PAYRESERVE);

        return response()->json([
            'status' => 'success',
        ]);
    }

    /**
     * Применение изменений
     * 
     * @param string $name
     * @param int $value
     * 
     * @return void
     */
    public function setChange(string $name, int $value): void
    {
        $model = Configuration::where('name', $name)->first();
        $model->value = $value;
        $model->save();
    }
}