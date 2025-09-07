# Cost Calculator

The Cost Calculator determines pricing for AI image upscaling based on image dimensions and resolution multipliers.

## Pricing Structure

**Base Costs**
- Upsampler API: $0.04 per credit
- Credit calculation: 0.25 credits per megapixel
- Default markup: 500% (6x cost multiplier)
- Stripe minimum payment: $0.50

**Resolution Multipliers**
- 4x upscaling: 16x increase in total pixels
- 8x upscaling: 64x increase in total pixels

## Calculation Methods

### `calculate_cost_detailed()`
Returns comprehensive pricing breakdown.

**Parameters**
- `$image_data` - Array with width/height
- `$resolution` - Resolution multiplier (4x, 8x)

**Returns**
```php
array(
    'input_megapixels' => 4.0,
    'output_megapixels' => '64.0MP',
    'credits_required' => 16.0,
    'upsampler_cost' => 0.64,
    'markup_percentage' => 500,
    'customer_price' => 3.84,
    'profit_margin' => 3.20
)
```

### `calculate_simple_price()`
Returns customer-facing price only.

**Parameters**
- `$image_data` - Array with width/height  
- `$resolution` - Resolution multiplier

**Returns**
- `float` - Final customer price

## Price Validation

**Minimum Price Enforcement**
- Stripe minimum of $0.50 automatically applied
- Small image handling with appropriate scaling
- Error handling for invalid dimensions

**Cost Components**
1. Calculate output megapixels: `(width × height × multiplier²) ÷ 1,000,000`
2. Calculate credits needed: `megapixels × 0.25`
3. Calculate base cost: `credits × $0.04`
4. Apply markup: `base_cost × (1 + markup_percentage/100)`
5. Enforce minimum: `max($0.50, final_price)`

## Configuration Options

**Customizable Settings**
- `smi_markup_percentage` - Profit margin control
- Upsampler cost per credit (constant)
- Stripe minimum payment (constant)

**Resolution Support**
- 4x: Standard quality upscaling
- 8x: Premium quality upscaling
- Extensible for additional resolutions

## Integration Points

**Usage Locations**
- REST API pricing calculation
- Modal price display
- Job cost tracking
- Revenue analytics
- Admin job management