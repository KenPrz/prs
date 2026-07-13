import * as React from "react"

import { cn } from "@/lib/utils"

function Textarea({ className, ...props }: React.ComponentProps<"textarea">) {
  return (
    <textarea
      data-slot="textarea"
      className={cn(
        // Carbon field: gray fill, bottom rule only, 2px blue focus outline
        "placeholder:text-muted-foreground flex field-sizing-content min-h-16 w-full rounded-none border-0 border-b border-muted-foreground/60 bg-muted px-3 py-2 text-base transition-[color,box-shadow,outline] outline-none focus-visible:outline-2 focus-visible:outline-ring focus-visible:-outline-offset-2 aria-invalid:outline-2 aria-invalid:outline-destructive aria-invalid:-outline-offset-2 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm",
        className
      )}
      {...props}
    />
  )
}

export { Textarea }
