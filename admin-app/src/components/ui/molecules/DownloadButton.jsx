import { useState } from 'react';
import ArrowDownTrayIcon from '@heroicons/react/24/outline/ArrowDownTrayIcon';
import Button from '../atoms/Button';

/**
 * Download Button Component
 *
 * Button to download orders as CSV or Excel
 * Handles the API call and triggers file download
 */
const DownloadButton = ({ filters = {}, format = 'csv', disabled = false }) => {
  const [isDownloading, setIsDownloading] = useState(false);

  /**
   * Handle download click
   */
  const handleDownload = async () => {
    if (isDownloading || disabled) return;

    setIsDownloading(true);

    try {
      // Build query parameters
      const params = new URLSearchParams({
        ...filters,
        format: format,
      });

      // Make request to export endpoint
      const response = await fetch(
        `${window.squidlyData.apiUrl}/orders/export?${params.toString()}`,
        {
          method: 'GET',
          headers: {
            'X-WP-Nonce': window.squidlyData.nonce,
          },
          credentials: 'include',
        }
      );

      if (!response.ok) {
        throw new Error('Failed to download orders');
      }

      // Get the blob from response
      const blob = await response.blob();

      // Create download link
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;

      // Set filename from Content-Disposition header or use default
      const contentDisposition = response.headers.get('Content-Disposition');
      let filename = `orders-export-${new Date().toISOString().split('T')[0]}.csv`;

      if (contentDisposition) {
        const filenameMatch = contentDisposition.match(/filename="(.+)"/);
        if (filenameMatch) {
          filename = filenameMatch[1];
        }
      }

      link.download = filename;

      // Trigger download
      document.body.appendChild(link);
      link.click();

      // Cleanup
      document.body.removeChild(link);
      window.URL.revokeObjectURL(url);
    } catch (error) {
      console.error('Download error:', error);
      alert('שגיאה בהורדת הקובץ. אנא נסה שוב.');
    } finally {
      setIsDownloading(false);
    }
  };

  return (
    <Button
      onClick={handleDownload}
      disabled={disabled || isDownloading}
      className="inline-flex items-center gap-2"
    >
      <ArrowDownTrayIcon className="w-5 h-5" />
      {isDownloading ? 'מוריד...' : 'הורד'}
    </Button>
  );
};

export default DownloadButton;
