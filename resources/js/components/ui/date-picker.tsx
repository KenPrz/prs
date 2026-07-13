import * as React from "react"
import { format, parseISO, isValid } from "date-fns"
import { Calendar as CalendarIcon } from "lucide-react"

import { cn } from "@/lib/utils"
import { Button } from "@/components/ui/button"
import { Calendar } from "@/components/ui/calendar"
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover"

interface DatePickerProps {
  date?: string | Date;
  onChange: (date: string) => void;
  placeholder?: string;
  disabled?: boolean;
  className?: string;
}

export function DatePicker({
  date,
  onChange,
  placeholder = "Pick a date",
  disabled = false,
  className,
}: DatePickerProps) {
  const parsedDate = React.useMemo(() => {
    if (!date) return undefined;
    if (date instanceof Date) return date;
    const p = parseISO(date);
    return isValid(p) ? p : undefined;
  }, [date]);

  const handleSelect = (date: Date | undefined) => {
    onChange(date ? format(date, "yyyy-MM-dd") : "");
  };

  return (
    <Popover>
      <PopoverTrigger asChild>
        <Button
          variant={"outline"}
          className={cn(
            "w-full justify-start text-left font-normal border-input bg-muted/20 hover:bg-muted/30 disabled:opacity-100 disabled:bg-muted/30 disabled:text-foreground",
            !parsedDate && "text-muted-foreground",
            className
          )}
          disabled={disabled}
        >
          <CalendarIcon className="mr-2 h-4 w-4" />
          {parsedDate ? format(parsedDate, "PPP") : <span>{placeholder}</span>}
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-auto p-0" align="start">
        <Calendar
          mode="single"
          selected={parsedDate}
          onSelect={handleSelect}
          initialFocus
        />
      </PopoverContent>
    </Popover>
  )
}
