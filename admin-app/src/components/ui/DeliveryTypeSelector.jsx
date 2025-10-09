import React from 'react';
import DropdownButton from './DropdownButton.jsx';

const DeliveryTypeSelector = ({
  value = null,
  onChange = () => {},
  className = ''
}) => {
  const deliveryOptions = [
    { value: null, label: 'הכל' },
    { value: 'delivery', label: 'משלוח' },
    { value: 'takeaway', label: 'איסוף עצמי' }
  ];

  const handleChange = (selectedValue) => {
    onChange(selectedValue);
  };

  return (
    <div className={className}>
      <DropdownButton
        options={deliveryOptions}
        value={value}
        onChange={handleChange}
        placeholder="סוג משלוח..."
        getOptionLabel={(option) => option.label}
        getOptionValue={(option) => option.value}
        width="w-48"
      />
    </div>
  );
};

export default DeliveryTypeSelector;
