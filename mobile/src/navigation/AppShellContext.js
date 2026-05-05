import { createContext, useContext } from 'react';

export const AppShellContext = createContext({
  isInAppShell: false,
  exitToWelcome: () => {},
  switchRole: () => {},
});

export function useAppShell() {
  return useContext(AppShellContext);
}
