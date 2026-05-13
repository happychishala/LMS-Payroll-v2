<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    use HasFactory;
    protected $fillable = [
        'expense_name',
        'expense_amount',
        'expense_vendor',
        'expense_attachment',
        'expense_date',
                
    ];


    public function expense_category()
    {
        
        return $this->belongsTo(ExpenseCategory::class, 'category_id','id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('expense_attachment')
            ->acceptsMimeTypes([
                'application/pdf',
                'image/jpeg',
                'image/png',
                'application/zip',
                'application/x-zip-compressed',
            ]);
    }

}
