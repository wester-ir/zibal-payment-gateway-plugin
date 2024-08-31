<div class="card mt-3">
    <div class="card-content form">
        <div class="form-control">
            <label for="merchant" after="{{ __('Required') }}">{{ __('ZibalPaymentGateway::attributes.merchant') }}</label>
            <input type="text" id="merchant" name="merchant" value="{{ old('merchant', $paymentGateway->data->get('merchant')) }}" class="default">
            <x-input-error :messages="$errors->get('merchant')" class="mt-2" />
        </div>

        <div class="form-control">
            <label for="type">{{ __('Type') }}</label>
            <select class="default" id="type" name="type">
                <option value="eager" @selected(old('type', $paymentGateway->data->get('type')) === 'eager')>Eager</option>
                <option value="lazy" @selected(old('type', $paymentGateway->data->get('type')) === 'lazy')>Lazy</option>
            </select>
            <x-input-error :messages="$errors->get('type')" class="mt-2" />
        </div>
    </div>
</div>
