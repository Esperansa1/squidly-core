import React from 'react';
import DropdownButton from './DropdownButton.jsx';

const BranchSelector = ({
  branches = [],
  selectedBranchId = 0,
  selectedBranchName = 'כל הסניפים',
  onBranchChange = () => {},
  showAllBranches = true,
  placeholder = 'בחר סניף...',
  disabled = false,
  className = ''
}) => {
  // Filter out any placeholder branches and prepare options
  const branchOptions = branches
    .filter(branch =>
      branch.name !== 'כל הסניפים' &&
      branch.name !== 'All Branches' &&
      branch.id !== 0
    )
    .map(branch => ({
      value: branch.id,
      label: branch.name,
      data: branch
    }));

  // Add "All Branches" option if needed
  if (showAllBranches) {
    branchOptions.unshift({
      value: 0,
      label: 'כל הסניפים',
      data: { id: 0, name: 'כל הסניפים' }
    });
  }

  const handleChange = (value, option) => {
    onBranchChange(option.data);
  };

  return (
    <div className={className}>
      <DropdownButton
        options={branchOptions}
        value={selectedBranchId}
        onChange={handleChange}
        placeholder={placeholder}
        disabled={disabled}
        getOptionLabel={(option) => option.label}
        getOptionValue={(option) => option.value}
        width="w-48"
      />
    </div>
  );
};

export default BranchSelector;