import * as React from "react"

import { cn } from "@/lib/utils"

function Input({ className, type, ...props }: React.ComponentProps<"input">) {
  return (
    <input
      type={type}
      data-slot="input"
      className={cn(
        // Carbon field: gray fill, bottom rule only, 2px blue focus outline
        "file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground flex h-9 w-full min-w-0 rounded-none border-0 border-b border-muted-foreground/60 bg-muted px-3 py-1 text-base transition-[color,box-shadow,outline] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-100 disabled:border-transparent disabled:bg-secondary disabled:text-muted-foreground md:text-sm",
        "focus-visible:outline-2 focus-visible:outline-ring focus-visible:-outline-offset-2",
        "aria-invalid:outline-2 aria-invalid:outline-destructive aria-invalid:-outline-offset-2",
        className
      )}
      {...props}
    />
  )
}

export { Input }
