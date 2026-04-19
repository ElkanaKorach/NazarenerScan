import { Stack } from 'expo-router';
import { StatusBar } from 'expo-status-bar';

export default function RootLayout() {
  return (
    <>
      <StatusBar style="dark" />
      <Stack screenOptions={{ headerShown: false }}>
        <Stack.Screen name="(tabs)" />
        <Stack.Screen
          name="result/[barcode]"
          options={{
            headerShown: true,
            title: 'Scan-Ergebnis',
            headerBackTitle: 'Scanner',
            presentation: 'card',
          }}
        />
        <Stack.Screen
          name="submit/[barcode]"
          options={{
            headerShown: true,
            title: 'Zur Prüfung einreichen',
            headerBackTitle: 'Zurück',
            presentation: 'modal',
          }}
        />
      </Stack>
    </>
  );
}
