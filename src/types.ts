export interface ServiceItem {
  id: string;
  title: string;
  desc: string;
  icon: string;
}

export interface QuoteBreakdown {
  basePrice: number;
  transportCost: number;
  surchargeTemp: number;
  surchargeUrgency: number;
  surchargeInsurance: number;
  fuelSurcharge: number;
  tollEstimation: number;
  tva: number;
  totalTTC: number;
}

export interface QuoteResult {
  trackingCode: string;
  serviceLabel: string;
  departureCity: string;
  arrivalCity: string;
  distance: number;
  etaString: string;
  co2Footprint: number;
  breakdown: QuoteBreakdown;
}
