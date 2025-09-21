import React from 'react';

const AvailabilityDisplay = ({
  availability,
  selectedBranchId,
  branches = [],
  strings = {}
}) => {
  // When "All Branches" is selected (selectedBranchId === 0), show available branch names
  if (selectedBranchId === 0) {
    const availableBranches = branches
      .filter(branch =>
        branch.id !== 0 &&
        branch.name !== 'כל הסניפים' &&
        branch.name !== 'All Branches' &&
        availability && availability[branch.id] === true
      )
      .map(branch => branch.name);

    if (availableBranches.length === 0) {
      return (
        <div className="flex items-center justify-center gap-2 w-full">
          <span className="text-sm text-gray-800 font-medium">
            {strings.not_available_anywhere || 'לא זמין באף סניף'}
          </span>
        </div>
      );
    }

    const branchText = availableBranches.join(', ');

    return (
      <div className="flex items-center justify-center gap-2 w-full">
        <span className="text-sm text-gray-800 font-medium" title={branchText}>
          {branchText}
        </span>
      </div>
    );
  }

  // For specific branch selection, show available/unavailable status
  const isAvailable = availability && availability[selectedBranchId] !== false;

  if (isAvailable) {
    return (
      <div className="flex items-center justify-center gap-2 w-full">
        <span className="text-sm text-green-700 font-medium">
          {strings.available || 'זמין'}
        </span>
        <div className="w-2 h-2 rounded-full bg-green-500" />
      </div>
    );
  }

  return (
    <div className="flex items-center justify-center gap-2 w-full">
      <span className="text-sm text-red-700 font-medium">
        {strings.unavailable || 'לא זמין'}
      </span>
      <div className="w-2 h-2 rounded-full bg-red-500" />
    </div>
  );
};

export default AvailabilityDisplay;